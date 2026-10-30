# Demo Style Development Notes

ZJMF addons can contain `config`, `controller`, `controller/clientarea`, `lang`, `template/admin`, `template/clientarea`, `template/public`, and `validate`.

Caiwu keeps that package concept but maps execution into explicit runtime actions:

- `addon.metadata`
- `addon.health_check`

Admin or client pages are not rendered through addon actions: the addons domain currently only supports schedule extensions, and `addon.admin.index` / `addon.client.index` / `addon.public.assets` were removed because they never had a platform consumer. Future admin or client pages should call explicit backend actions instead of loading addon controllers dynamically.
