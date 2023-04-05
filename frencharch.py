import xml.etree.ElementTree as ET
# from io import StringIO, BytesIO
import json
import re
from collections import defaultdict
import functools
try:
    import requests
except ImportError:
    pass

from color import Color

constant_styles = dict( # the original ones from assnat
    enceinte="fill:#9c9c9c;stroke:#fff;stroke-width:0.3;stroke-linejoin:round;",
    bancs="fill:#fff;stroke:#cbcbcb;stroke-width:0.8;stroke-linejoin:round;",
    perchoir="fill:#fff;stroke:#cbcbcb;stroke-width:0.4;stroke-linejoin:round;",
    ministres="fill:#003c68;stroke:#fff;stroke-width:0.3;stroke-linejoin:round;",
    commissions="fill:#3b88b2;stroke:#fff;stroke-width:0.3;stroke-linejoin:round;",
) | dict( # some replacements and an addition
    ministres="fill:#9c9c9c;stroke:#fff;stroke-width:0.3;stroke-linejoin:round;",
    commissions="fill:#b7b7b7;stroke:#fff;stroke-width:0.3;stroke-linejoin:round;",
    vacant="fill:#ddd;stroke:#fff;stroke-width,0.3;stroke-linejoin:round;",
)

def build_svg(**toggles):
    """
    Returns an svg xml node.
    The kwargs toggle different parts being included or not.
    They take the keys of constant_styles, and "vacant" for the vacant seats.
    """
    toggles = dict(enceinte=False, perchoir=False, vacant=True) | toggles

    groups = sorted(set(number_to_group.values()))

    svg = ET.Element('svg',
        xmlns="http://www.w3.org/2000/svg",
        # width="900", height="600",
        viewBox="0 0 850 475",
    )

    # vanity comment
    svg.append(
        ET.Comment("Created with the Wikimedia french parliament diagram generator (...)"),
    )

    # style definition
    style = [""]
    # fixed elements of the decor
    style.extend("."+name+"{"+st+"}" for name, st in constant_styles.items())
    # groups
    for k, g in enumerate(groups):
        color = group_color(g)
        style.append(f".grp{k}{{fill:{color.hex};stroke:#2D2D2D;stroke-width:0.3;stroke-linejoin:round;}}")
    # assembling
    style_element = ET.Element("style", type="text/css")
    style_element.text = "\n\t".join(style)+"\n"
    svg.append(style_element)

    # the paths
    for name, path in paths.items():
        # a seat
        if name.isdecimal():
            grp = number_to_group.get(int(name))
            if grp is None:
                if not toggles["vacant"]:
                    continue
                cl = "vacant"
            else:
                props = number_to_properties[int(name)]
                cl = f"grp{groups.index(grp)}"
        # a fixed element
        else:
            if not toggles.get(name, True):
                continue
            if name == "bancsdevant":
                name = "bancs"
            cl = name

        # the svg path
        elem = ET.Element("path",
            {"class":cl},
            d=path,
        )

        # the hover/tooltip/title
        if cl not in ("enceinte", "bancs", "perchoir"):
            title = ET.Element("title")
            if cl == "vacant":
                title.text = "Siège vacant"
            elif cl == "ministres":
                title.text = "Bancs des ministres"
            elif cl == "commissions":
                title.text = "Bancs des commissions"
            else:
                title.text = title_from_properties(props)
            elem.append(title)

        svg.append(elem)

    # make it a bit more readable
    svg.text = "\n"
    for el in svg:
        el.tail = "\n"

    return svg

@functools.cache
def group_color(group_name, fallback=True):
    """
    Returns a color for a group.
    I tried to make this as future-proof as possible wrt the database naming conventions.
    Returns a Color.
    """
    cf = group_name.casefold()

    def inn(*a):
        for a in a:
            if a in cf:
                return True
        return False

    if "renaissance" in cf:
        rv = "#ffeb00"
    elif "socialiste" in cf:
        rv = "#ff8080"
    elif "horizon" in cf:
        rv = "#adc1fd"
    elif "inscrit" in cf: # non-inscrits
        rv = "#ddd"
    elif inn("insoumis"):
        rv = "#cc2443"
    elif inn("gdr", "républicaine", "republicaine", "communiste"):
        rv = "#ff001c"
    elif inn("démocrate"): # après GDR
        rv = "#f90"
    elif inn("républic", "republic"): # après GDR
        rv = "#06c"
    elif inn("écolo", "écologiste", "ecolo", "ecologiste", "vert", "eelv"):
        rv = "#00c000"
    elif inn("rassemblement"):
        rv = "#0d378a"
    elif inn("indépendant", "independant"):
        rv = "#a38ebc"

    elif fallback:
        return Color.random()
    else:
        raise ValueError(f"Could not find a color for {group_name!r}")

    return Color.from_hex(rv)


## SVG paths part
# about seeking the SVG paths to draw each numbered seat

def create_json(f=None, write=True):
    """
    Reads the hemi.js, and from the instructions creating the svg file, creates a json file with the paths.

    There are the "enceinte", "bancs", "bancsdevant", "perchoir", "ministres" and "commissions" paths,
    and ints from 0 to about 650 (with discontinuities).
    The exact number of integer seats is not documented.
    """
    if f is None:
        with open("./hemi.js", "r") as f:
            return create_json(f, write=write)

    if isinstance(f, str):
        js = f
    else:
        js = f.read()

    db = {}

    # find the named paths
    for name in ("enceinte", "bancs", "bancsdevant", "perchoir", "ministres", "commissions"):
        path = re.search(r"hemi\."+name+r" *= *hemicycle.path\(\"([^\"\n]+)\"\)", js).group(1)
        db[name] = path

    # find the numbered paths
    for match in re.finditer(r"hemi\.s([0-9]+) *= *hemicycle.path\(\"([^\"\n]+)\"\).+\'([0-9]+)\'", js):
        assert match.group(1) == match.group(3)
        db[match.group(1)] = match.group(2)

    if write:
        with open("./french_hemi.json", "w") as _f:
            json.dump(db, _f, indent=4)
    else:
        return json.dumps(db, indent=4)

paths = None # type: dict[str, str]
# number or name (ministres, commissions...) as a string -> d= svg path

if __name__ == "__main__":
    # try to update the hemi.js file
    # maybe do that only if requested ?
    # with a third option of just writing the json to french_hemi.json ?
    try:
        _r = requests.get("https://www2.assemblee-nationale.fr/extension/ezswidl-anobjects/design/an/javascript/svg/hemi.js")
    except Exception:
        pass
    else:
        paths = json.loads(create_json(_r.text, write=False))

if paths is None:
    # import the paths from the json file
    with open("./french_hemi.json", "r") as _f:
        paths = json.load(_f)


## Seating part
# about associating each seat with a person (and in extenso, a group and a color)

def get_datadiv():
    """
    Ideally, should not be called more than once.
    """
    r = requests.get("https://www2.assemblee-nationale.fr/deputes/hemicycle")

    # with open("./hemicycle.html", "wb") as f:
    #     f.write(r.content)

    # doesn't work, because the html is not well-formed xml because of the <script> tag
    # tree = ET.parse("./hemicycle.html")
    # tree = ET.fromstring(r.content)

    # didn't work either for some reason
    # content = r.content.decode("utf-8")
    # content = re.sub(r"<script[^>]*>.*</script>", "", content, flags=re.DOTALL)
    # tree = ET.fromstring(content)
    # return tree

    rawdata = re.search(r"(<div id=\"data\">.+?</div>)", r.text, re.DOTALL).group(1)
    datadiv = ET.fromstring(rawdata)
    return datadiv

number_to_group = {}
number_to_properties = {}

def generate_number_to_group(properties=True):
    global number_to_group
    global number_to_properties

    datadiv = get_datadiv()
    number_to_group = {}
    for dl in datadiv:
        group = dl.get("data-groupe")
        place = dl.get("data-place")
        number = int(place.removeprefix("s"))

        number_to_group[number] = group
        if properties:
            number_to_properties[number] = dict(
                civ=dl.get("data-civ"),
                nom=dl.get("data-nom"),
                id=dl.get("data-id"),
                dept=dl.get("data-departement"),
                circo=re.search(r"\(?(\d+)[\w\s]*\)?", dl.get("data-circo")).group(1),
            )

# only do that if some json file doesn't exist, or if requested ?
try:
    generate_number_to_group()
except Exception:
    pass

def title_from_properties(props):
    civ = props["civ"]
    nom = props["nom"]
    if None in (civ, nom):
        return None
    rv = f"{civ} {nom}"

    dept = props["dept"]
    circo = props["circo"]
    if None in (dept, circo):
        return rv
    rv += f"\n {circo}e circo. {dept}"
    return rv

# funny but unused
def get_group_to_numbers():
    datadiv = get_datadiv()
    group_to_numbers = defaultdict(set)
    for dl in datadiv:
        group = dl.get("data-groupe")
        place = dl.get("data-place")
        number = int(place.removeprefix("s"))

        group_to_numbers[group].add(number)

    group_to_numbers.default_factory = None

    return group_to_numbers


def main():
    tree = ET.ElementTree(build_svg())
    with open("./frencharch.svg", "wb") as f:
        tree.write(f, encoding="utf-8", xml_declaration=True)

if __name__ == "__main__":
    main()
