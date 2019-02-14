
import sys
import argparse
from dotnet_pkg_info.build_commands import get_build_settings_and_commands
import json


if __name__ == '__main__':
    get_build_settings_and_commands(sys.argv[1], sys.argv[2])

