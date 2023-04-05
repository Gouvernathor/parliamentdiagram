from collections import namedtuple
import random

class Color(namedtuple("Color", "r g b a", defaults=(255,))):
    __slots__ = ()

    @classmethod
    def from_hex(cls, hex):
        hex = hex.removeprefix("#")

        if len(hex) in (3, 4):
            hex = "".join(c*2 for c in hex)

        return cls(*[int(hex[i:i+2], 16) for i in range(0, len(hex), 2)])

    def to_hex(self):
        rv = f"#{self.r:02x}{self.g:02x}{self.b:02x}"
        if self.a != 255:
            rv += f"{self.a:02x}"
        return rv

    @property
    def hex(self):
        return self.to_hex()

    @classmethod
    def random(cls, alpha=False):
        return cls(*(random.randrange(256) for _ in range(4 if alpha else 3)))

    def replace(self, **kwargs):
        return self._replace(**kwargs)

    def __repr__(self):
        return f"{self.__class__.__name__}({', '.join(str(c) for c in self)})"

Color.red = Color(255, 0, 0)
Color.green = Color(0, 255, 0)
Color.blue = Color(0, 0, 255)
Color.white = Color(255, 255, 255)
Color.black = Color(0, 0, 0)
