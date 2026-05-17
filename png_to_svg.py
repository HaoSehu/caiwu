from __future__ import annotations

import argparse
import xml.etree.ElementTree as ET
from collections import defaultdict
from pathlib import Path
from typing import DefaultDict, Dict, Iterable, List, Optional, Sequence, Tuple

from PIL import Image, ImageFilter


FIXED_INPUT_DIR = Path(r"C:\Users\cloud_user\Desktop\png")
FIXED_OUTPUT_DIR = Path(r"C:\Users\cloud_user\Desktop\svg")
SVG_NS = "http://www.w3.org/2000/svg"
WORDMARK_TITLE = "创欧云"
WORDMARK_SUBTITLE = "www.coyjs.cn"
WORDMARK_FONT_STACK = "'Microsoft YaHei', 'PingFang SC', 'Noto Sans CJK SC', sans-serif"

Point = Tuple[float, float]
GridPoint = Tuple[int, int]
GridEdge = Tuple[GridPoint, GridPoint]

ET.register_namespace("", SVG_NS)

if hasattr(Image, "Resampling"):
    LANCZOS = Image.Resampling.LANCZOS
else:
    LANCZOS = Image.LANCZOS


def format_number(value: float) -> str:
    rounded = round(float(value), 3)
    if rounded.is_integer():
        return str(int(rounded))
    return ("{:.3f}".format(rounded)).rstrip("0").rstrip(".")


def rgb_to_hex(color: Tuple[int, int, int]) -> str:
    return "#{:02X}{:02X}{:02X}".format(*color)


def detect_dominant_opaque_color(
    img: Image.Image,
    alpha_threshold: int = 240,
    bucket_size: int = 6,
) -> Tuple[int, int, int]:
    rgba = img.convert("RGBA")
    colors = rgba.getcolors(maxcolors=20_000_000) or []
    buckets: Dict[Tuple[int, int, int], Dict[str, float]] = {}

    for count, color in colors:
        if len(color) != 4:
            continue
        red, green, blue, alpha = color
        if alpha < alpha_threshold:
            continue

        key = (red // bucket_size, green // bucket_size, blue // bucket_size)
        bucket = buckets.setdefault(key, {"count": 0.0, "r": 0.0, "g": 0.0, "b": 0.0})
        bucket["count"] += count
        bucket["r"] += count * red
        bucket["g"] += count * green
        bucket["b"] += count * blue

    if not buckets and alpha_threshold > 160:
        return detect_dominant_opaque_color(
            img=img,
            alpha_threshold=160,
            bucket_size=bucket_size,
        )

    if not buckets:
        return (94, 182, 249)

    best_bucket = max(buckets.values(), key=lambda item: item["count"])
    total = max(best_bucket["count"], 1.0)
    return (
        int(round(best_bucket["r"] / total)),
        int(round(best_bucket["g"] / total)),
        int(round(best_bucket["b"] / total)),
    )


def find_content_bounds(alpha: Image.Image, threshold: int = 8) -> Optional[Tuple[int, int, int, int]]:
    width, height = alpha.size
    xs: List[int] = []
    ys: List[int] = []

    for y in range(height):
        for x in range(width):
            if alpha.getpixel((x, y)) > threshold:
                xs.append(x)
                ys.append(y)

    if not xs:
        return None

    return min(xs), min(ys), max(xs) + 1, max(ys) + 1


def get_horizontal_runs(
    img: Image.Image,
    threshold: int = 8,
    min_pixels_per_column: int = 2,
) -> List[Tuple[int, int]]:
    rgba = img.convert("RGBA")
    alpha = rgba.getchannel("A")
    width, height = rgba.size
    occupancy = [sum(1 for y in range(height) if alpha.getpixel((x, y)) > threshold) for x in range(width)]

    runs: List[Tuple[int, int]] = []
    start: Optional[int] = None
    for index, value in enumerate(occupancy + [0]):
        present = value > min_pixels_per_column
        if present and start is None:
            start = index
        elif start is not None and not present:
            runs.append((start, index))
            start = None

    return runs


def get_vertical_runs(
    img: Image.Image,
    threshold: int = 8,
    min_pixels_per_row: int = 5,
) -> List[Tuple[int, int]]:
    rgba = img.convert("RGBA")
    alpha = rgba.getchannel("A")
    width, height = rgba.size
    occupancy = [sum(1 for x in range(width) if alpha.getpixel((x, y)) > threshold) for y in range(height)]

    runs: List[Tuple[int, int]] = []
    start: Optional[int] = None
    for index, value in enumerate(occupancy + [0]):
        present = value > min_pixels_per_row
        if present and start is None:
            start = index
        elif start is not None and not present:
            runs.append((start, index))
            start = None

    return runs


def should_use_text_wordmark(img: Image.Image) -> bool:
    width, height = img.size
    if height == 0 or width / float(height) < 2.5:
        return False

    runs = get_horizontal_runs(img)
    if len(runs) < 4:
        return False

    first_width = runs[0][1] - runs[0][0]
    other_widths = [right - left for left, right in runs[1:]]
    return bool(other_widths) and first_width >= max(other_widths) * 1.5


def extract_icon_region(img: Image.Image) -> Tuple[Image.Image, int, int]:
    rgba = img.convert("RGBA")
    runs = get_horizontal_runs(rgba)

    if not runs:
        return rgba, 0, 0

    start, end = runs[0]
    crop = rgba.crop((start, 0, end + 1, rgba.size[1]))
    bounds = find_content_bounds(crop.getchannel("A"))
    if not bounds:
        return rgba, 0, 0

    left, top, right, bottom = bounds
    return crop.crop((left, top, right, bottom)), start + left, top


def measure_text_wordmark_layout(img: Image.Image) -> Dict[str, int]:
    runs = get_horizontal_runs(img)
    if len(runs) < 4:
        raise ValueError("无法从原图中识别图标和文字区域。")

    width, height = img.size
    icon_img, icon_x, icon_y = extract_icon_region(img)
    text_left = runs[1][0]
    text_right = runs[-1][1]
    text_region = img.crop((text_left, 0, text_right, height))
    vertical_runs = get_vertical_runs(text_region)
    if len(vertical_runs) < 2:
        raise ValueError("无法从原图中识别标题与副标题区域。")

    title_top, title_bottom = vertical_runs[0]
    subtitle_top, subtitle_bottom = vertical_runs[-1]

    title_crop = text_region.crop((0, title_top, text_region.size[0], title_bottom))
    subtitle_crop = text_region.crop((0, subtitle_top, text_region.size[0], subtitle_bottom))
    title_bbox = title_crop.getbbox() or (0, 0, text_region.size[0], title_bottom - title_top)
    subtitle_bbox = subtitle_crop.getbbox() or (0, 0, text_region.size[0], subtitle_bottom - subtitle_top)

    return {
        "canvas_width": width,
        "canvas_height": height,
        "icon_x": icon_x,
        "icon_y": icon_y,
        "title_x": text_left + title_bbox[0],
        "title_y": title_top + title_bbox[1],
        "title_width": title_bbox[2] - title_bbox[0],
        "title_height": title_bbox[3] - title_bbox[1],
        "subtitle_x": text_left + subtitle_bbox[0],
        "subtitle_y": subtitle_top + subtitle_bbox[1],
        "subtitle_width": subtitle_bbox[2] - subtitle_bbox[0],
        "subtitle_height": subtitle_bbox[3] - subtitle_bbox[1],
    }


def build_alpha_mask(
    img: Image.Image,
    alpha_threshold: int,
    blur_radius: float,
    upscale_factor: int,
) -> Tuple[Image.Image, int]:
    alpha = img.convert("RGBA").getchannel("A")

    if blur_radius > 0:
        alpha = alpha.filter(ImageFilter.GaussianBlur(blur_radius))

    if upscale_factor > 1:
        width, height = alpha.size
        alpha = alpha.resize((width * upscale_factor, height * upscale_factor), resample=LANCZOS)

    mask = alpha.point(lambda value: 255 if value >= alpha_threshold else 0)
    mask = mask.filter(ImageFilter.MedianFilter(3))
    mask = mask.point(lambda value: 255 if value >= 128 else 0)
    return mask, upscale_factor


def extract_boundary_edges(mask: Image.Image) -> List[GridEdge]:
    pixels = mask.load()
    width, height = mask.size
    edges: List[GridEdge] = []

    for y in range(height):
        for x in range(width):
            if pixels[x, y] == 0:
                continue

            if y == 0 or pixels[x, y - 1] == 0:
                edges.append(((x, y), (x + 1, y)))
            if x == width - 1 or pixels[x + 1, y] == 0:
                edges.append(((x + 1, y), (x + 1, y + 1)))
            if y == height - 1 or pixels[x, y + 1] == 0:
                edges.append(((x + 1, y + 1), (x, y + 1)))
            if x == 0 or pixels[x - 1, y] == 0:
                edges.append(((x, y + 1), (x, y)))

    return edges


def choose_next_point(
    previous_point: GridPoint,
    current_point: GridPoint,
    candidates: Sequence[GridPoint],
) -> GridPoint:
    if len(candidates) == 1:
        return candidates[0]

    direction_order = {
        (1, 0): 0,
        (0, 1): 1,
        (-1, 0): 2,
        (0, -1): 3,
    }
    incoming = (
        current_point[0] - previous_point[0],
        current_point[1] - previous_point[1],
    )
    incoming_index = direction_order[incoming]
    rotation_rank = {1: 0, 0: 1, 3: 2, 2: 3}

    def sort_key(candidate: GridPoint) -> Tuple[int, int, int]:
        outgoing = (
            candidate[0] - current_point[0],
            candidate[1] - current_point[1],
        )
        outgoing_index = direction_order[outgoing]
        rotation = (outgoing_index - incoming_index) % 4
        return (
            rotation_rank.get(rotation, 4),
            candidate[1],
            candidate[0],
        )

    return min(candidates, key=sort_key)


def trace_loops_from_edges(edges: Iterable[GridEdge]) -> List[List[GridPoint]]:
    adjacency: DefaultDict[GridPoint, List[GridPoint]] = defaultdict(list)
    for start, end in edges:
        adjacency[start].append(end)

    unused_edges = set(edges)
    loops: List[List[GridPoint]] = []

    while unused_edges:
        start_edge = next(iter(unused_edges))
        start_point, current_point = start_edge
        previous_point = start_point
        loop = [start_point]
        unused_edges.remove(start_edge)

        while True:
            loop.append(current_point)
            if current_point == start_point:
                break

            candidates = [
                next_point
                for next_point in adjacency[current_point]
                if (current_point, next_point) in unused_edges
            ]
            if not candidates:
                break

            next_point = choose_next_point(previous_point, current_point, candidates)
            unused_edges.remove((current_point, next_point))
            previous_point, current_point = current_point, next_point

        if len(loop) >= 4 and loop[0] == loop[-1]:
            loops.append(loop[:-1])

    return loops


def collapse_collinear_points(points: Sequence[Point]) -> List[Point]:
    if len(points) < 3:
        return list(points)

    collapsed: List[Point] = []
    total = len(points)

    for index in range(total):
        previous_point = points[index - 1]
        current_point = points[index]
        next_point = points[(index + 1) % total]

        vector_a = (
            current_point[0] - previous_point[0],
            current_point[1] - previous_point[1],
        )
        vector_b = (
            next_point[0] - current_point[0],
            next_point[1] - current_point[1],
        )
        cross = vector_a[0] * vector_b[1] - vector_a[1] * vector_b[0]

        if abs(cross) > 1e-9:
            collapsed.append(current_point)

    return collapsed or list(points)


def polygon_area(points: Sequence[Point]) -> float:
    area = 0.0
    total = len(points)
    for index in range(total):
        x1, y1 = points[index]
        x2, y2 = points[(index + 1) % total]
        area += x1 * y2 - x2 * y1
    return area / 2.0


def point_distance(point_a: Point, point_b: Point) -> float:
    delta_x = point_a[0] - point_b[0]
    delta_y = point_a[1] - point_b[1]
    return (delta_x * delta_x + delta_y * delta_y) ** 0.5


def point_to_segment_distance(point: Point, segment_start: Point, segment_end: Point) -> float:
    start_x, start_y = segment_start
    end_x, end_y = segment_end
    delta_x = end_x - start_x
    delta_y = end_y - start_y

    if delta_x == 0 and delta_y == 0:
        return point_distance(point, segment_start)

    projection = (
        ((point[0] - start_x) * delta_x + (point[1] - start_y) * delta_y)
        / float(delta_x * delta_x + delta_y * delta_y)
    )
    projection = max(0.0, min(1.0, projection))
    projected_point = (start_x + projection * delta_x, start_y + projection * delta_y)
    return point_distance(point, projected_point)


def simplify_closed_polyline(
    points: Sequence[Point],
    tolerance: float,
    minimum_points: int = 24,
    max_passes: int = 12,
) -> List[Point]:
    simplified = list(points)

    for _ in range(max_passes):
        total = len(simplified)
        if total <= minimum_points:
            break

        next_points: List[Point] = []
        changed = False

        for index in range(total):
            previous_point = simplified[index - 1]
            current_point = simplified[index]
            next_point = simplified[(index + 1) % total]
            distance = point_to_segment_distance(current_point, previous_point, next_point)
            remaining_points = len(next_points) + (total - index - 1)

            if distance <= tolerance and remaining_points >= minimum_points:
                changed = True
                continue

            next_points.append(current_point)

        if not changed:
            break

        simplified = next_points

    return simplified


def chaikin_smooth(points: Sequence[Point], iterations: int = 2, ratio: float = 0.25) -> List[Point]:
    smoothed = list(points)
    if len(smoothed) < 3:
        return smoothed

    for _ in range(iterations):
        next_points: List[Point] = []
        total = len(smoothed)

        for index in range(total):
            point_a = smoothed[index]
            point_b = smoothed[(index + 1) % total]
            first_point = (
                (1.0 - ratio) * point_a[0] + ratio * point_b[0],
                (1.0 - ratio) * point_a[1] + ratio * point_b[1],
            )
            second_point = (
                ratio * point_a[0] + (1.0 - ratio) * point_b[0],
                ratio * point_a[1] + (1.0 - ratio) * point_b[1],
            )
            next_points.extend([first_point, second_point])

        smoothed = next_points

    return smoothed


def reduce_dense_points(points: Sequence[Point], minimum_distance: float) -> List[Point]:
    if len(points) < 3:
        return list(points)

    reduced = [points[0]]

    for point in points[1:]:
        if point_distance(point, reduced[-1]) >= minimum_distance:
            reduced.append(point)

    if len(reduced) > 1 and point_distance(reduced[0], reduced[-1]) < minimum_distance:
        reduced.pop()

    return reduced if len(reduced) >= 3 else list(points)


def sample_closed_polyline(points: Sequence[Point], step_distance: float) -> List[Point]:
    if len(points) < 3:
        return list(points)

    sampled = [points[0]]
    carried_distance = 0.0
    total = len(points)

    for index in range(1, total + 1):
        current_point = points[index % total]
        previous_point = points[index - 1]
        segment_length = point_distance(previous_point, current_point)
        carried_distance += segment_length

        if carried_distance >= step_distance:
            sampled.append(current_point)
            carried_distance = 0.0

    return sampled if len(sampled) >= 3 else list(points)


def build_curve_path(points: Sequence[Point], tension: float = 0.82) -> str:
    if len(points) < 3:
        return ""

    if len(points) < 5:
        command = ["M {} {}".format(format_number(points[0][0]), format_number(points[0][1]))]
        for x_coord, y_coord in points[1:]:
            command.append("L {} {}".format(format_number(x_coord), format_number(y_coord)))
        command.append("Z")
        return " ".join(command)

    curve_scale = tension / 6.0
    command = ["M {} {}".format(format_number(points[0][0]), format_number(points[0][1]))]
    total = len(points)

    for index in range(total):
        point_0 = points[(index - 1) % total]
        point_1 = points[index]
        point_2 = points[(index + 1) % total]
        point_3 = points[(index + 2) % total]

        control_1 = (
            point_1[0] + (point_2[0] - point_0[0]) * curve_scale,
            point_1[1] + (point_2[1] - point_0[1]) * curve_scale,
        )
        control_2 = (
            point_2[0] - (point_3[0] - point_1[0]) * curve_scale,
            point_2[1] - (point_3[1] - point_1[1]) * curve_scale,
        )

        command.append(
            "C {} {} {} {} {} {}".format(
                format_number(control_1[0]),
                format_number(control_1[1]),
                format_number(control_2[0]),
                format_number(control_2[1]),
                format_number(point_2[0]),
                format_number(point_2[1]),
            )
        )

    command.append("Z")
    return " ".join(command)


def loops_to_path_data(
    loops: Sequence[Sequence[GridPoint]],
    scale: int,
    min_area: float,
) -> str:
    path_parts: List[str] = []

    for loop in loops:
        scaled_points = collapse_collinear_points(
            [(point[0] / float(scale), point[1] / float(scale)) for point in loop]
        )

        if len(scaled_points) < 3:
            continue

        if abs(polygon_area(scaled_points)) < min_area:
            continue

        sampled_points = sample_closed_polyline(scaled_points, step_distance=7.5)
        smoothed_points = chaikin_smooth(sampled_points, iterations=3, ratio=0.25)
        reduced_points = reduce_dense_points(smoothed_points, minimum_distance=1.8)
        path_data = build_curve_path(reduced_points)
        if not path_data:
            path_data = build_curve_path(sampled_points)
        if path_data:
            path_parts.append(path_data)

    return " ".join(path_parts)


def trace_monochrome_icon_to_svg(
    img: Image.Image,
    fill_color: str,
    alpha_threshold: int,
    blur_radius: float,
    upscale_factor: int,
    min_area: float,
) -> str:
    mask, scale = build_alpha_mask(
        img=img,
        alpha_threshold=alpha_threshold,
        blur_radius=blur_radius,
        upscale_factor=upscale_factor,
    )
    edges = extract_boundary_edges(mask)
    loops = trace_loops_from_edges(edges)
    path_data = loops_to_path_data(loops=loops, scale=scale, min_area=min_area)

    if not path_data:
        raise ValueError("未能从图标中提取有效轮廓。")

    root = ET.Element(
        ET.QName(SVG_NS, "svg"),
        {
            "version": "1.1",
            "width": str(img.size[0]),
            "height": str(img.size[1]),
            "viewBox": "0 0 {} {}".format(img.size[0], img.size[1]),
        },
    )

    ET.SubElement(
        root,
        ET.QName(SVG_NS, "path"),
        {
            "d": path_data,
            "fill": fill_color,
            "fill-rule": "evenodd",
            "shape-rendering": "geometricPrecision",
        },
    )

    return ET.tostring(root, encoding="utf-8", xml_declaration=True).decode("utf-8")


def svg_to_group(svg_string: str, offset_x: int = 0, offset_y: int = 0) -> ET.Element:
    root = ET.fromstring(svg_string)
    group = ET.Element(ET.QName(SVG_NS, "g"))

    for child in list(root):
        transform_parts = []
        if offset_x or offset_y:
            transform_parts.append("translate({}, {})".format(offset_x, offset_y))
        existing_transform = child.attrib.get("transform")
        if existing_transform:
            transform_parts.append(existing_transform)
        if transform_parts:
            child.set("transform", " ".join(transform_parts))
        group.append(child)

    return group


def build_text_logo_svg(icon_svg: str, layout: Dict[str, int], title_fill: str) -> str:
    root = ET.Element(
        ET.QName(SVG_NS, "svg"),
        {
            "version": "1.1",
            "width": str(layout["canvas_width"]),
            "height": str(layout["canvas_height"]),
            "viewBox": "0 0 {} {}".format(layout["canvas_width"], layout["canvas_height"]),
        },
    )

    root.append(svg_to_group(icon_svg, offset_x=layout["icon_x"], offset_y=layout["icon_y"]))

    title_font_size = max(120, int(layout["title_height"] * 1.05))
    subtitle_font_size = max(44, int(layout["subtitle_height"] * 0.92))
    subtitle_target_width = max(100, layout["subtitle_width"])

    title = ET.SubElement(
        root,
        ET.QName(SVG_NS, "text"),
        {
            "x": str(layout["title_x"]),
            "y": str(layout["title_y"] + layout["title_height"]),
            "font-size": str(title_font_size),
            "font-family": WORDMARK_FONT_STACK,
            "font-weight": "700",
            "fill": title_fill,
        },
    )
    title.text = WORDMARK_TITLE

    subtitle = ET.SubElement(
        root,
        ET.QName(SVG_NS, "text"),
        {
            "x": str(layout["subtitle_x"]),
            "y": str(layout["subtitle_y"] + layout["subtitle_height"]),
            "font-size": str(subtitle_font_size),
            "font-family": WORDMARK_FONT_STACK,
            "font-weight": "600",
            "textLength": str(subtitle_target_width),
            "lengthAdjust": "spacing",
            "fill": title_fill,
        },
    )
    subtitle.text = WORDMARK_SUBTITLE

    return ET.tostring(root, encoding="utf-8", xml_declaration=True).decode("utf-8")


def png_to_svg(
    input_path: Path,
    output_path: Path,
    alpha_threshold: int = 168,
    blur_radius: float = 1.0,
    upscale_factor: int = 6,
    min_area: float = 6.0,
) -> None:
    if not input_path.exists():
        raise FileNotFoundError("输入文件不存在: {}".format(input_path))

    img = Image.open(str(input_path)).convert("RGBA")

    if should_use_text_wordmark(img):
        icon_img, _, _ = extract_icon_region(img)
        fill_color = rgb_to_hex(detect_dominant_opaque_color(icon_img))
        icon_svg = trace_monochrome_icon_to_svg(
            img=icon_img,
            fill_color=fill_color,
            alpha_threshold=alpha_threshold,
            blur_radius=blur_radius,
            upscale_factor=upscale_factor,
            min_area=min_area,
        )
        title_fill = "#FFFFFF" if "_w" in input_path.stem.lower() else "#111111"
        layout = measure_text_wordmark_layout(img)
        svg_string = build_text_logo_svg(icon_svg=icon_svg, layout=layout, title_fill=title_fill)
    else:
        fill_color = rgb_to_hex(detect_dominant_opaque_color(img))
        svg_string = trace_monochrome_icon_to_svg(
            img=img,
            fill_color=fill_color,
            alpha_threshold=alpha_threshold,
            blur_radius=blur_radius,
            upscale_factor=upscale_factor,
            min_area=min_area,
        )

    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(svg_string, encoding="utf-8")
    print("已生成 SVG: {}".format(output_path.resolve()))


def convert_directory(
    input_dir: Path,
    output_dir: Path,
    alpha_threshold: int,
    blur_radius: float,
    upscale_factor: int,
    min_area: float,
) -> None:
    if not input_dir.exists() or not input_dir.is_dir():
        raise NotADirectoryError("输入目录不存在: {}".format(input_dir))

    output_dir.mkdir(parents=True, exist_ok=True)
    png_files = sorted(path for path in input_dir.iterdir() if path.is_file() and path.suffix.lower() == ".png")

    if not png_files:
        print("未找到 PNG 文件，当前输入目录: {}".format(input_dir.resolve()))
        return

    print("输入目录: {}".format(input_dir.resolve()))
    print("输出目录: {}".format(output_dir.resolve()))
    print("共发现 {} 个 PNG 文件，开始转换。\n".format(len(png_files)))

    for png_file in png_files:
        output_file = output_dir / "{}.svg".format(png_file.stem)
        png_to_svg(
            input_path=png_file,
            output_path=output_file,
            alpha_threshold=alpha_threshold,
            blur_radius=blur_radius,
            upscale_factor=upscale_factor,
            min_area=min_area,
        )

    print("\n转换完成，共生成 {} 个 SVG 文件。".format(len(png_files)))


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="将透明 PNG logo 转为适合图标的单色 SVG")
    parser.add_argument("--input-dir", type=Path, default=FIXED_INPUT_DIR, help="批量转换时的 PNG 输入目录")
    parser.add_argument("--output-dir", type=Path, default=FIXED_OUTPUT_DIR, help="批量转换时的 SVG 输出目录")
    parser.add_argument("--input-file", type=Path, default=None, help="单文件模式输入 PNG")
    parser.add_argument("--output-file", type=Path, default=None, help="单文件模式输出 SVG")
    parser.add_argument("--alpha-threshold", type=int, default=168, help="alpha 二值化阈值")
    parser.add_argument("--blur-radius", type=float, default=1.0, help="alpha 预平滑半径")
    parser.add_argument("--upscale-factor", type=int, default=6, help="轮廓追踪前的放大倍数")
    parser.add_argument("--min-area", type=float, default=6.0, help="过滤碎片轮廓的最小面积")
    return parser


def main() -> None:
    parser = build_parser()
    args = parser.parse_args()

    if args.input_file:
        output_file = args.output_file or args.output_dir / "{}.svg".format(args.input_file.stem)
        png_to_svg(
            input_path=args.input_file,
            output_path=output_file,
            alpha_threshold=args.alpha_threshold,
            blur_radius=args.blur_radius,
            upscale_factor=args.upscale_factor,
            min_area=args.min_area,
        )
        return

    convert_directory(
        input_dir=args.input_dir,
        output_dir=args.output_dir,
        alpha_threshold=args.alpha_threshold,
        blur_radius=args.blur_radius,
        upscale_factor=args.upscale_factor,
        min_area=args.min_area,
    )


if __name__ == "__main__":
    main()
