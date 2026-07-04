---
name: php-unserialize-audit
description: "Audit PHP engine deserialization surface including unserialize, session decoders, WDDX, phar metadata, custom unserialize handlers, Serializable implementers, and magic-method dispatch during deserialization. Use for back-reference UAF, type confusion, partial-object destructors, signed-length heap overflow, and parse inconsistency. For general non-deserialization PHP memory-safety bugs, use a broader PHP audit workflow instead."
---

# PHP Unserialize Surface Audit

Purpose: find exploitable bugs in PHP's deserialization surface and related object-lifetime code that is reachable through deserialization. For general PHP engine memory-safety bugs outside the deserialization flow (e.g. the `extract()` callback-reentrancy UAF, `array_walk` callback mutations, generic hash-slot UAFs), use the companion `/php-audit` skill.

The advisory corpus at `work/phpcodz/research/pch-0{10,11,14,15,19,20,21,22,25,26,27,28,29,30,31,32,33,34}.md` is the ground truth for what "exploitable" looks like here — re-read one or two before starting if you are unfamiliar.

## Usage

```
/php-unserialize-audit <target> [focus]
```

Target can be:
- an extension name: `spl`, `date`, `gmp`, `soap`, `session`, `phar`, `standard`, `sqlite3`, `pdo`, `intl`, `wddx`
- a specific file: `ext/spl/spl_array.c`, `ext/standard/var_unserializer.re`
- a class/subsystem: `SplObjectStorage`, `SoapFault`, `Exception::getTraceAsString`
- a broad category: `unserialize`, `session-deserializer`, `magic-methods`, `custom-unserializers`, `all`

Examples:
- `/php-unserialize-audit spl`
- `/php-unserialize-audit unserialize "UAF via R: back-references"`
- `/php-unserialize-audit ext/date/php_date.c`
- `/php-unserialize-audit session "multi-entry decode UAF"`
- `/php-unserialize-audit magic-methods "type confusion in __toString"`
- `/php-unserialize-audit all "double-free"`

## Source tree

PHP sources live at `/home/x/kernel/php_old/work/src/php-<version>/`. The primary target versions are PHP 5.4.x–5.6.x (matches the advisory corpus), but the same bug classes appear in PHP 7.x with different zval layout. Check which version the user intends; if unspecified, default to `php-5.5.14` (the version most PoCs target). Extract the tarball if no extracted tree exists:

```
cd /home/x/kernel/php_old/work/src && tar xzf php-5.5.14.tar.gz
```

## Bug taxonomy (derived from phpcodz corpus)

Every class below has at least one confirmed instance in the advisory corpus. Use this as the hunting checklist. For each finding, map it to a U-class so the synthesis pass can deduplicate.

### U1 — Custom `ce->unserialize` handler frees zval still tracked in var_hash
An extension implements `ce->unserialize` (e.g. `spl_array_object_unserialize`, `spl_object_storage_unserialize`, `spl_dllist_unserialize`, `gmp_unserialize`). Inside:

1. `ALLOC_INIT_ZVAL(pvar)` creates a fresh zval.
2. `php_var_unserialize(&pvar, &p, max, &var_hash)` parses one value — **during that call, pvar gets appended to `var_hash->data[]` via `var_push_dtor_no_addref` so future `R:n` / `r:n` back-references can resolve to it**.
3. Later in the same handler, `zval_ptr_dtor(&pvar)`, `zval_dtor(&pvar)`, or `FREE_ZVAL(pvar)` drops refcount to zero and frees the zval value.
4. A subsequent `R:n` in the serialized stream (or from a later sibling in the outer array) still resolves to that freed slot — attacker controls the type/value byte on the freed chunk.

Confirmed instances: pch-027 (ArrayObject `pflags`, `pmembers`), pch-028 (SplObjectStorage `pcount`, `pmembers`), pch-029 (SplDoublyLinkedList `flags`), pch-032 (GMP `zv_ptr`).

**Fix pattern to distinguish good code from bad**: correct handlers use `var_push_dtor_no_addref` themselves before dropping ref, or never call `zval_ptr_dtor` on zvals that went through `php_var_unserialize`.

### U2 — Magic method (`__wakeup`, `__toString`, `__destruct`) freeing a property mid-deserialization
`object_common2` invokes `__wakeup` after `process_nested_data` populates properties. A user-defined `__wakeup` that calls `unset($this->foo)` or `$this->foo = 1` drops the refcount of the old property zval — but that old zval was registered in var_hash, so an outer `R:n` still resolves to it.

Confirmed: pch-021 [CVE-2015-2787] (generic `__wakeup` UAF), pch-020 [CVE-2015-0273] (DateTime), pch-033 (SplObjectStorage + `__wakeup`), pch-034 (SplDoublyLinkedList + `__wakeup`), pch-019 (DateTimeZone `__wakeup` type confusion).

### U3 — `convert_to_long` / `convert_to_string` / `convert_to_double` on a var_hash-tracked zval
Implicit destruction: converting an array/object zval in-place frees the old value. If the caller got the zval from `php_var_unserialize` and didn't re-register it, `R:` references to it UAF.

Confirmed: pch-022 (DateInterval's `PHP_DATE_INTERVAL_READ_PROPERTY` macro).

### U4 — `process_nested_data` failure path frees partially-registered zvals
Inside `process_nested_data`, if an inner `php_var_unserialize` fails, the function calls `zval_dtor(data); FREE_ZVAL(data);` and returns 0 — but `data` may already be in var_hash from a nested call that succeeded partway.

Confirmed: pch-030 case (ii).

### U5 — `Serializable::unserialize` reentrancy freeing already-registered state
A PHP class implementing `Serializable` can inside its `unserialize()` method call `unserialize()` recursively, free local state, or reassign `$this->data = 1`. The outer deserializer's var_hash still holds references to the freed zvals.

Confirmed: pch-030 cases (i) and (iii).

### U6 — Multi-entry decoder loop with inter-iteration `zval_ptr_dtor`
Session decoders (`PS_SERIALIZER_DECODE_FUNC(php)`, `php_binary`, `php_serialize`) loop over multiple key=value pairs, call `php_var_unserialize` per value, then `zval_ptr_dtor(&current)` — the next iteration can reference the freed slot via `R:n`.

Confirmed: pch-031.

### U7 — Type confusion via missing `Z_TYPE_P` check before `Z_STRVAL_P` / `Z_LVAL_P` / `Z_ARRVAL_P` / `Z_OBJ_HT_P`
Magic methods and formatters read properties via `zend_read_property` then dereference without type check. Attacker supplies a serialized object whose property is a long instead of a string: `Z_STRVAL_P` treats the long as a pointer → arbitrary read, or vice-versa leaks pointers as integer values.

Confirmed: pch-025 (SoapFault `__toString`), pch-023 (SoapClient), pch-026 (Exception `getTraceAsString` — fake HashTable via fake Bucket).

### U8 — Signed-int length into `memcpy` / `erealloc`
`int len` parameter used in `erealloc(ptr, *len + l + 1)` followed by `memcpy(..., l)` with `l = vallen` from a fake string-type zval. Negative `vallen` passes signed check, becomes huge unsigned in memcpy → heap overflow.

Confirmed: pch-026 (`TRACE_APPEND_STRL`).

### U9 — Partial object `__destruct` on malformed serialized stream
`unserialize()` parses `O:len:"classname":elements:{` successfully (instantiates the class), then parse fails mid-property. Return value is destroyed, refcount hits 0, `__destruct()` runs on a class the attacker picked. Lets you invoke `__destruct` at an arbitrary point in a script (before `$shutdown_functions` setup, etc.).

Confirmed: pch-011.

### U10 — Serialize/unserialize parse inconsistency (filter bypass)
`safeUnserialize`-style prefilters accept input that `unserialize_str` handles differently than the filter expects — e.g. length-prefix mismatches, whitespace/terminator handling. Allows re-introducing `O:` / `C:` into input that was supposed to be object-free.

Confirmed: pch-010 (IPB `safeUnserialize` bypass).

### U11 — HHVM / alternate engine discrepancies
Classname parsing differences between engines cause `unserialize()` to accept payloads that `var_export` later round-trips into executable code.

Confirmed: pch-015, pch-016. (Only relevant if HHVM is in scope — usually skip.)

### U12 — WDDX / session-handler deserialization with magic-method side channels
Same `__wakeup`/`__destruct` primitives as unserialize(), reached via a different decoder. WDDX is historically under-audited.

Confirmed: pch-014.

### Adjacent classes (not in phpcodz but always worth checking when auditing PHP)

- **Double-free**: same zval passed to `zval_ptr_dtor` on two paths of an error goto chain. Grep for `goto outexcept` / `goto error` inside custom unserializers and diff which zvals have been `ALLOC_INIT_ZVAL`-ed vs freed on each path.
- **Refcount underflow**: `zval_ptr_dtor` on a zval whose refcount was never incremented after a transfer.
- **Uninitialized `Z_TYPE`**: `ALLOC_ZVAL(x)` without `INIT_ZVAL` / `INIT_PZVAL` → stack garbage becomes type tag.
- **Integer overflow in `count * sizeof`**: in `zend_hash_copy` size computations, allocation sizing based on attacker-controlled count.
- **Phar metadata unserialize**: `phar_parse_*` unserializes phar metadata on `file_exists` / `stat` / `fopen` — reachable via `phar://` stream wrapper from any file op on attacker-controlled input.

## Detection heuristics

Grep gives candidates; the agent must verify by reading the full function and the shared context. Never report a raw grep match as a finding.

**For U1, U3, U4, U6** (custom unserializer / convert_to / loop UAFs):
```
ripgrep -n 'php_var_unserialize' <target_files>
```
For each hit, check within the same function for:
- `zval_ptr_dtor` / `zval_dtor` / `FREE_ZVAL` on the unserialized zval
- `convert_to_long` / `convert_to_string` / `convert_to_double` / `convert_to_boolean` on it
- Loop context (`while`, `for`) with unserialize inside and dtor between iterations

**For U2, U9** (magic-method UAFs, partial __destruct):
```
ripgrep -n 'call_user_function_ex|zend_call_method|BG\(serialize_lock\)|__wakeup|__destruct|__toString' <target_files>
```
Trace the caller — is it inside `object_common2` / unserialize flow? Can the callee reassign `$this` properties whose zvals were registered in var_hash?

**For U5** (Serializable reentrancy):
```
ripgrep -n 'ce->unserialize\s*==\s*NULL|ce->unserialize(rval' Zend/ ext/standard/var_unserializer.re
```
Check paths where a user-defined `unserialize()` can re-enter `php_var_unserialize` on the same `var_hash`.

**For U7** (type confusion):
```
ripgrep -nB5 'Z_STRVAL_P|Z_LVAL_P|Z_ARRVAL_P|Z_OBJ_HT_P|Z_OBJPROP_P' <target_files>
```
For each hit, verify the preceding 5–10 lines contain a matching `Z_TYPE_P(...) == IS_STRING` / `IS_LONG` / `IS_ARRAY` check on the same zval, OR that the zval was produced by a function that guarantees type.

**For U8** (signed-length memcpy/erealloc):
```
ripgrep -n 'erealloc.*\+.*len|memcpy.*len' <target_files>
```
Check the declared type of `len` and whether it can be negative or attacker-controlled.

**For U10** (serialize/unserialize inconsistency):
Read `unserialize_str` in `var_unserializer.re` side-by-side with any `safe_unserialize`-style prefilter in the target. Compare length-prefix handling, embedded quotes, terminator semantics.

**Double-free**:
```
ripgrep -n 'zval_ptr_dtor|FREE_ZVAL' <target_files> | look for same variable name twice in one function across different goto paths
```

## Target discovery (auto-enumerate — do not rely on a hardcoded list)

Do NOT start from a hardcoded file map. The attack surface is defined by code patterns, not filenames. Run the discovery queries below across the entire source tree and union the results — every hit is a potential target.

### D1. Classes registering a custom `ce->unserialize`
```
rg -n --no-heading 'ce->unserialize\s*=|->unserialize\s*=\s*\w+_unserialize|INIT_CLASS_ENTRY.*unserialize' ext/ Zend/
rg -n --no-heading 'zend_class_unserialize_t|ZEND_FN\(.*unserialize' ext/ Zend/
```
Every matched function is an unserializer handler. Record: function name, containing class, file:line, and the file it lives in.

### D2. Classes implementing magic methods (`__wakeup`, `__toString`, `__destruct`, `__set_state`, `__sleep`)
```
rg -n --no-heading '"__wakeup"|"__toString"|"__destruct"|"__set_state"|"__sleep"|"__serialize"|"__unserialize"' ext/ Zend/
rg -n --no-heading 'PHP_ME\([^,]+,\s*__(wakeup|toString|destruct|set_state|sleep|serialize|unserialize)' ext/ Zend/
rg -n --no-heading 'ZEND_METHOD\([^,]+,\s*__(wakeup|toString|destruct|set_state|sleep|serialize|unserialize)' ext/ Zend/
```
These are the magic-method implementations. For each, record the class and method, and flag the file as a target for U2/U7 analysis. **Every class here that is serializable is a potential U2 vector** — the exploit is a user-defined subclass overriding the magic method, or the built-in magic method itself mutating state during unserialize.

### D3. Session / alternate decoders
```
rg -n --no-heading 'PS_SERIALIZER_DECODE_FUNC|PS_SERIALIZER_ENCODE_FUNC|ps_serializer\b' ext/
rg -n --no-heading 'php_wddx_deserialize|wddx_stack_destroy' ext/wddx/ 2>/dev/null
rg -n --no-heading 'phar_parse_metadata|phar_parse_.*file' ext/phar/ 2>/dev/null
```

### D4. Callers of `php_var_unserialize` outside the core deserializer
```
rg -n --no-heading 'php_var_unserialize' ext/ Zend/ sapi/
```
Every caller outside `ext/standard/var_unserializer.*` is a potential U1/U3/U6 site — it's someone wiring the deserializer into a new context where they may not respect var_hash rules.

### D5. Type-confusion risk: `zend_read_property` → `Z_*_P` without type guard
```
rg -n --no-heading -B2 -A6 'zend_read_property' ext/ Zend/
```
Every hit where the returned zval is dereferenced via `Z_STRVAL_P`, `Z_LVAL_P`, `Z_ARRVAL_P`, `Z_OBJ_HT_P`, or `Z_OBJPROP_P` without a `Z_TYPE_P(...) ==` check in the intervening lines is a U7 candidate.

### D6. Fake-HashTable / fake-Bucket risk: `Z_ARRVAL_P` / `HASH_OF` on attacker-reachable zval
```
rg -n --no-heading 'Z_ARRVAL_P|HASH_OF|zend_hash_apply_with_arguments' ext/ Zend/
```
Cross-reference against D5 — these are the pch-026 (Exception getTraceAsString) shape.

### D7. Signed-length memcpy / erealloc pairs
```
rg -n --no-heading -B1 -A3 'erealloc\b' ext/ Zend/ | rg -B1 -A3 'memcpy'
rg -n --no-heading 'int\s+\*?\w*len\b|int\s+vallen' ext/ Zend/
```
For each `erealloc(ptr, a + len + b)` + `memcpy(dst, src, len)` pair, check the declared type of `len`. `int` or `long` = U8 candidate.

### D8. Double-free candidates
```
rg -nU --multiline --no-heading 'zval_ptr_dtor\([^)]*\)[\s\S]{0,500}zval_ptr_dtor\([^)]*\)' ext/ Zend/
```
Then within each function, look for same-variable pairs across distinct `goto` paths.

### D9. Stream wrappers that trigger unserialize implicitly
```
rg -n --no-heading 'php_stream_wrapper_register|phar_url_stat|phar_wrapper' ext/
```
`phar://` is the big one — `file_exists`, `fopen`, `stat` on a `phar://` URL invokes phar metadata unserialize. Any function in an extension that does a file-type operation on user-controlled paths is a reachability vector.

### D10. Serializable interface implementers (U5 vectors)
```
rg -n --no-heading 'zend_ce_serializable|implements\s+Serializable|zend_class_implements.*serializable' ext/ Zend/
```

### Union the results

After running D1–D10, produce a deduplicated target list: `{file_path, reason, U-classes to check}`. This list is what Step 2 gathers context for and Step 3 partitions across agents.

### Known-target seed list (sanity check, NOT a substitute for D1–D10)

The advisory corpus confirms bugs in these files. Your D1–D10 discovery must find all of them; if it doesn't, your queries are broken. Use the seed list as a cross-check after discovery — every file below should appear in the union of D1–D10 results, and anything in D1–D10 that isn't here is a potentially under-audited target.

**Core deserializer:**
- `ext/standard/var_unserializer.re` + `var_unserializer.c` (generated) — the `R:` / `r:` / `C:` / `O:` state machine, `process_nested_data`, `object_common1`, `object_common2`, `finish_nested_data`, `var_push_dtor*`, `var_destroy`
- `ext/standard/var.c` — serialize side (needed for U10 inconsistency checks against `unserialize_str`)
- `Zend/zend_variables.c` — `zval_ptr_dtor` / `zval_dtor` / `_zval_ptr_dtor_wrapper` definitions
- `Zend/zend_hash.c` — HashTable / Bucket semantics, `zend_hash_copy`, `zend_hash_apply_with_arguments`
- `Zend/zend_execute_API.c` — `call_user_function_ex`, magic method dispatch
- `Zend/zend_API.c` — `convert_to_long` / `convert_to_string` / `convert_to_double` / `convert_to_boolean` (U3 semantics)
- `Zend/zend_operators.c` — same conversion fallbacks

**Classes with custom `ce->unserialize` (primary U1/U3/U4/U6 targets):**
- `ext/spl/spl_array.c` — ArrayObject, ArrayIterator *(pch-027)*
- `ext/spl/spl_observer.c` — SplObjectStorage, MultipleIterator *(pch-028, pch-033)*
- `ext/spl/spl_dllist.c` — SplDoublyLinkedList, SplStack, SplQueue *(pch-029, pch-034)*
- `ext/spl/spl_heap.c` — SplHeap, SplPriorityQueue, SplMinHeap, SplMaxHeap
- `ext/spl/spl_fixedarray.c` — SplFixedArray
- `ext/date/php_date.c` — DateTime, DateTimeImmutable, DateTimeZone, DateInterval, DatePeriod *(pch-019, pch-020, pch-022)*
- `ext/gmp/gmp.c` — GMP (5.6+) *(pch-032)*
- `ext/phar/phar.c` + `ext/phar/phar_object.c` — phar metadata unserialize (reachable via `phar://` stream wrapper)
- `ext/intl/*/*.c` — Collator, DateFormatter, MessageFormatter, NumberFormatter, ResourceBundle, several have custom (de)serializers
- `ext/standard/basic_functions.c` — any serialization callbacks

**Magic-method / type-punning risk (primary U2/U7 targets):**
- `Zend/zend_exceptions.c` — `getTraceAsString`, `__toString` on Exception *(pch-026)*
- `ext/soap/soap.c` — SoapFault `__toString`, SoapClient `__call` *(pch-023, pch-025)*
- `ext/spl/spl_exceptions.c`
- `ext/date/php_date.c` — DateTime `__wakeup`, DateTimeZone `__wakeup` *(pch-019, pch-020)*
- Any class in D2 that is serializable — user subclasses override the magic method and become the U2 payload

**Session deserializers (U6 targets):**
- `ext/session/session.c` — `PS_SERIALIZER_DECODE_FUNC` for `php`, `php_binary`, `php_serialize` *(pch-031)*
- `ext/session/mod_*.c` — file / user / memcache session handlers
- Any SAPI module that calls `php_session_decode`

**Alternate decoders:**
- `ext/wddx/wddx.c` — WDDX packet deserializer *(pch-014)*
- `ext/phar/phar.c` — phar metadata path (invokes `php_var_unserialize` on file metadata)
- `ext/standard/url.c` — `parse_str` edge cases (rarely, but historically problematic)

**Serializable-interface implementers (U5 targets):**
- Any class whose registration calls `zend_class_implements(ce, 1, zend_ce_serializable)` — discover via D10, not hardcoded

**Partial-object `__destruct` (U9 targets):**
- Any class defining `__destruct` — the reachability is via a malformed `O:` payload that aborts mid-parse after instantiation. Discover via D2, pair with U9 verification.

Use this list to validate discovery coverage. The actual agent work assignment comes from the D1–D10 union.

---

## Step 1 — Identify target files

Map the user's `<target>` argument to a concrete file set:
- Extension name → all `.c` and `.re` in `ext/<name>/`
- File path → that path (+ its private `.h` if related)
- Class name → grep for `ZEND_METHOD(<ClassName>` / `PHP_METHOD(<ClassName>` and `<classname>_object_handlers`
- Category (`unserialize`, `session-deserializer`, etc.) → use the Target file map above
- `all` → the full union, partitioned across agents by extension directory

If the argument is ambiguous, print what you found and ask. Never silently narrow.

---

## Step 2 — Context-gathering pass (REQUIRED, do not delegate)

Sub-agents only read their assigned files. The semantic facts that determine whether a bug exists — zval layout, var_hash registration rules, magic-method invocation points, `convert_to_*` side effects — live in different files. Produce a `SHARED_CONTEXT` block **yourself** and paste it verbatim into every agent prompt in Step 3.

### 2a. zval / HashTable / Bucket layout for the target PHP version

Read `Zend/zend.h` and `Zend/zend_types.h`. Extract:
- `struct _zval_struct` definition (PHP 5.x: pointer-based; PHP 7.x: inline with `zend_value` union) — include every field with exact types and offsets
- `struct _zval_gc_info`
- `struct bucket`
- `struct _hashtable` — list head, number used, refcount, destructor pointer
- The refcount macros: `Z_REFCOUNT_P`, `Z_ADDREF_P`, `Z_DELREF_P`, `Z_SET_REFCOUNT_P`
- Type-tag constants: `IS_NULL`, `IS_LONG`, `IS_DOUBLE`, `IS_BOOL`, `IS_ARRAY`, `IS_OBJECT`, `IS_STRING`, `IS_RESOURCE`

### 2b. var_hash / var_unserialize registration semantics

Read `ext/standard/var_unserializer.re` top-to-bottom and `ext/standard/php_var.h`. Extract:
- `php_unserialize_data_t` / `var_entries` structure
- When `var_push_dtor` is called vs `var_push_dtor_no_addref` vs `var_push`
- The `R:` and `r:` case blocks — exactly which array they index and whether they addref
- `var_destroy` — what it frees and in what order
- `process_nested_data`, `object_common1`, `object_common2` definitions

Key invariants to state plainly in the context block:
- "Any zval passed through `php_var_unserialize` successfully is appended to `var_hash->data[]` via `var_push_dtor_no_addref` with its current refcount, so `R:n` can resolve to it. Calling `zval_ptr_dtor` on such a zval without first guarding via `var_push_dtor` creates a UAF oracle."
- "`R:n` resolves to the zval itself (reference), `r:n` returns a copy of its value."
- "`convert_to_long/string/double/boolean` on an array or object zval calls `zval_dtor` on the old value before writing the new primitive."

### 2c. Magic method dispatch during unserialize

Grep for `__wakeup` / `__toString` / `__destruct` invocation sites:
```
ripgrep -n '"__wakeup"|"__toString"|"__destruct"|zend_user_unserialize' Zend/ ext/standard/
```
For each: which function calls it, under what state (is var_hash still active? has the object been added to var_hash yet?), and whether callbacks can mutate `$this` properties that are in var_hash.

### 2d. Custom unserializer vtable

For each file in the target set, extract:
- `ce->unserialize = X` assignments and the function `X` signature
- `PS_SERIALIZER_DECODE_FUNC(...)` bodies (function name, parameters, return convention)
- Whether the handler uses its own sub-`var_hash` (safer) or the caller's (UAF-prone)

### 2e. Cross-file callers of target-file unserializers

For each custom unserializer in the target, grep for callers:
```
ripgrep -n '<unserializer_function_name>' ext/ Zend/
```
Usually invoked from `var_unserializer.re`'s `C:` (custom) case — confirm the arguments and the caller's expectations about var_hash state.

### 2f. PHP version and affected-version matrix

Record which PHP version(s) the target codebase is. If multiple are extracted, confirm with the user. Map each finding to the affected-version matrix from the advisory corpus.

### Format the `SHARED_CONTEXT` block

```
=== SHARED CONTEXT (read by all audit agents) ===

--- PHP version ---
[e.g. 5.5.14 from /home/x/kernel/php_old/work/src/php-5.5.14]

--- zval / HashTable layout ---
[paste struct definitions verbatim, with refcount semantics]

--- var_hash registration rules ---
[state the three invariants about var_push_dtor_no_addref, R:/r: resolution, convert_to_* side effects]

--- Magic method dispatch ---
__wakeup: called from [file:line] after [state]. Can mutate: [yes/no] properties currently in var_hash.
__toString: ...
__destruct: ...

--- Custom unserializer vtable (target files) ---
<class>::<unserializer_function> (file:line): signature, uses own var_hash [yes/no], caller [file:line]

--- convert_to_* semantics ---
convert_to_long(zv): if Z_TYPE(zv) in {IS_ARRAY, IS_OBJECT}, calls zval_dtor(zv) before overwriting.
[... same for string/double/bool ...]

--- Known good-vs-bad patterns ---
GOOD: handler calls var_push_dtor_no_addref(&var_hash, &pvar) before zval_ptr_dtor(&pvar).
BAD:  handler calls zval_ptr_dtor(&pvar) directly (enables R:n UAF).

=== END SHARED CONTEXT ===
```

Keep it factual and terse. No analysis yet.

---

## Step 3 — Launch parallel sonnet agents

Launch one Agent per file (or small related-file group — e.g. `spl_dllist.c` + `spl_dllist.h` in one agent). All in a single message, all `model="sonnet"`. Every file in the target must be covered; do not cap or skip.

Each agent prompt MUST contain these sections in order:

**Section A — Context**

"You are auditing PHP <version> for exploitable memory-safety bugs in the unserialize surface: UAF, double-free, type confusion, heap overflow via signed-length, partial-object __destruct, parse inconsistency. The advisory corpus at `work/phpcodz/research/pch-0{10,11,14,15,19,20,21,22,25,26,27,28,29,30,31,32,33,34}.md` is the ground truth for bug shape."

**Section B — Shared context (paste VERBATIM)**

Paste the `SHARED_CONTEXT` block. Do not summarize.

**Section C — File assignment**

"Read the ENTIRE file at <path>. Use the shared context to resolve any struct, macro, or function defined elsewhere. Do not skim."

**Section D — Bug classes to hunt**

Paste the U1–U12 taxonomy above verbatim, plus the adjacent classes (double-free, refcount underflow, uninitialized Z_TYPE, phar metadata).

**Section E — Verification requirements per class**

For EACH candidate finding, the agent must explicitly state:

- **U1/U3/U4/U6**: the exact line where the zval is registered in var_hash (implicit via `php_var_unserialize`), the exact line it is freed, and the R:/r: offset that resolves to the freed slot. If the agent can't construct a serialized string that reaches the UAF, downgrade to LOW confidence.
- **U2**: the exact property whose zval is in var_hash, the exact line where `__wakeup`/`__toString`/`__destruct` is dispatched, and the exact PHP code the attacker would put in the magic method to free that property.
- **U5**: the exact lines of recursion through `php_var_unserialize` on the same var_hash.
- **U7**: the exact `Z_*_P` access and the absence of a type check in the preceding basic block (not just the preceding line — walk back through the CFG).
- **U8**: the exact signed type of the length variable and a negative value that passes the signed check but is attacker-controlled.
- **U9**: the exact object_common* path where refcount hits 0 with __destruct registered.
- **Double-free**: the two distinct call sites of `zval_ptr_dtor` / `FREE_ZVAL` on the same zval across a goto chain.

"Do NOT report speculative patterns. If you can't name the line where the bad thing happens, don't file it."

**Section F — Noise filter**

"Do NOT report: missing NULL checks on malloc (PHP uses emalloc which longjmps on OOM), code style, deprecated API usage, pure bug-class pattern matches without a reachable trigger."

**Section G — Output format**

For each finding: (a) file:line (b) one-line summary (c) U-class (U1–U12 or "other: <name>") (d) trigger payload sketch (serialized string) (e) reachability: which PHP-facing function reaches the vuln (`unserialize`, `session_decode`, `phar://` stream open, `__wakeup` of user class, etc.) (f) primitive: UAF-read / UAF-write / type-confusion-read / heap-overflow / __destruct-at-arbitrary-point / RCE (g) confidence HIGH/MEDIUM/LOW with justification tied to Section E.

---

## Step 4 — Opus verification pass

After all sonnet agents complete, collect every finding rated **HIGH or MEDIUM** in U1–U9 or double-free. Launch ONE `model="opus"` agent (or parallelize per-finding if >6 findings) with:

- The `SHARED_CONTEXT` block
- The finding text verbatim
- Instruction: "Read the exact file and function. Construct the serialized string (or session-decode string, or phar metadata) that triggers the vuln. Show the refcount trace of the vulnerable zval from allocation to free to re-use. If you cannot produce a PoC-shaped input, downgrade to LOW."

Opus output per finding: CONFIRMED (with PoC sketch) / REJECTED (with reason) / NEEDS-MORE-DATA (with specific question).

Do not use opus for the initial sweep — only verification.

---

## Step 5 — Synthesize

Present results as a table:

| File:Line | Bug | U-class | Trigger | Primitive | Confidence |

Group by primitive severity (RCE > UAF-write > UAF-read > type-confusion-read > heap-overflow > __destruct-arb > crash). For each CONFIRMED finding, include the opus PoC sketch inline.

Cross-reference against the phpcodz corpus: if a finding matches an existing advisory (same file, same function, same class), note the advisory number — this is a rediscovery, valuable for confirming the audit methodology but not a new bug unless the version is post-patch.

---

## Anti-patterns to reject upfront

- "This function calls `emalloc` without checking return value" — emalloc never returns NULL; it longjmps.
- "This code is deprecated in PHP 7" — scope is the version under audit.
- "Attacker could pass a very long string" — PHP has `max_input_vars` and memory_limit; not a finding without a specific bypass.
- "`Z_STRVAL_P` without explicit type check" — valid if the preceding code path guarantees `IS_STRING` (e.g. string-only deserializer branch). Require CFG reasoning, not a single-line grep match.
- "`zval_ptr_dtor` after `php_var_unserialize`" — valid if `var_push_dtor_no_addref` was called, or if the zval was re-initialized (`ALLOC_INIT_ZVAL` then a new `php_var_unserialize` into a different slot). Require tracing the specific zval, not the variable name.

---

## One-shot mode

If the user asks for a quick scan without the full context pass (e.g. "just grep for the convert_to_long pattern in ext/date"), skip Steps 2–4 and run the Detection heuristics directly, but prefix every output with "UNVERIFIED CANDIDATE — context pass was skipped." Do not rate confidence above LOW in one-shot mode.
