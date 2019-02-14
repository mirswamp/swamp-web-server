
import sys
import argparse
from dotnet_pkg_info import dotnet_pkg
from dotnet_pkg_info import merge
from dotnet_pkg_info import build_commands
import json


def process_cli_args():

    cli_parser = argparse.ArgumentParser(prog='dotnet_pkg_info',
                                         description='''Get package information, build settings and build commands''')

    cli_parser.add_argument('package',
                            default=None,
                            nargs='?',
                            help='Path to the package directory or a solution file or a project file')
    
    cli_parser.add_argument('--format',
                            choices=['json', 'text'],
                            default='json',
                            required=False,
                            help='gets the version of the program')

    cli_parser.add_argument('--no-config',
                            required=False,
                            default=False,
                            action='store_true',
                            help='Do not display configuration information')

    cli_parser.add_argument('--no-framework',
                            required=False,
                            default=False,
                            action='store_true',
                            help='Do not display target framework information')

    cli_parser.add_argument('--build-settings',
                            required=False,
                            help='Validate and Merge build settings')

    cli_parser.add_argument('--build-commands',
                            required=False,
                            help='Validate and Merge build settings')

    group = cli_parser.add_mutually_exclusive_group(required=False)

    group.add_argument('--src-file-types',
                       required=False,
                       default=False,
                       action='store_true',
                       help='List of dotnet source file extensions')

    group.add_argument('--framework-types',
                       required=False,
                       default=False,
                       action='store_true',
                       help='List of frameworks available')

    group.add_argument('--proj-file-types',
                       required=False,
                       default=False,
                       action='store_true',
                       help='Path to the package directory or a solution file or a project file')

    return cli_parser.parse_args()


def pretty_print(pkg_info, indent=4):

    def pprint(indent, value):
        print('{indent}{value}'.format(indent=' ' * indent, value=value))

    curr_indent = 0
    for tag in pkg_info.keys():
        pprint(curr_indent, tag)

        curr_indent = curr_indent + 4

        if tag == dotnet_pkg.DotnetPackage.SLN_FILES_TAG:
            for sln_file in pkg_info[dotnet_pkg.DotnetPackage.SLN_FILES_TAG].keys():
                pprint(curr_indent, sln_file)
                next_indent = curr_indent + 4
                for proj_file in pkg_info[dotnet_pkg.DotnetPackage.SLN_FILES_TAG][sln_file]:
                    pprint(next_indent, proj_file)
        else:
            for proj_file in pkg_info[dotnet_pkg.DotnetPackage.PROJ_FILES_TAG].keys():
                pprint(curr_indent, proj_file)

                next_indent = curr_indent + 4
                for _key in pkg_info[dotnet_pkg.DotnetPackage.PROJ_FILES_TAG][proj_file].keys():
                    pprint(next_indent, _key)

                    next_indent2 = next_indent + 4
                    if type(pkg_info[dotnet_pkg.DotnetPackage.PROJ_FILES_TAG][proj_file][_key]) == list:
                        for _item in pkg_info[dotnet_pkg.DotnetPackage.PROJ_FILES_TAG][proj_file][_key]:
                            pprint(next_indent2, _item)
                    else:
                            pprint(next_indent2, pkg_info[dotnet_pkg.DotnetPackage.PROJ_FILES_TAG][proj_file][_key])

        curr_indent = curr_indent - 4


if __name__ == '__main__':
    parser = process_cli_args()
    args = vars(parser)

    if args['build_commands']:
        build_commands.main(args['build_commands'], args['format'] == 'json')
    if args['build_settings'] and args['package']:
        new_pkg_info = merge.main(args['build_settings'], args['package'])
        json.dump(new_pkg_info, sys.stdout)
    elif args['src_file_types']:
        json.dump(dotnet_pkg.DotnetPackage.DOTNET_SRC_FILE_TYPES, sys.stdout)
    elif args['framework_types']:
        json.dump(dotnet_pkg.DotnetPackage.DOTNET_FRAMEWORK_TYPES, sys.stdout)
    elif args['proj_file_types']:
        json.dump(dotnet_pkg.DotnetPackage.DOTNET_PROJ_FILE_TYPES, sys.stdout)
    elif args['package']:
        dpi = dotnet_pkg.main(args['package'])

        if args['no_config']:
            dpi.remove_config()

        if args['no_framework']:
            dpi.remove_framework()

        if args['format'] == 'json':
            json.dump(dpi.to_json(), sys.stdout)
        else:
            pretty_print(dpi.to_json())
