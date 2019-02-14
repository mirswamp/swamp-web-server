
import sys
import argparse
from dotnet_pkg_info.dotnet_pkg_info import DotnetPackageInfo
from dotnet_pkg_info.build_commands import verify_and_merge
import json


def process_cli_args():

    cli_parser = argparse.ArgumentParser(prog='dotnet_pkg_info',
                                         description='''Get build settings, Validates build settings''')

    cli_parser.add_argument('package',
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

        if tag == DotnetPackageInfo.SLN_FILES_TAG:
            for sln_file in pkg_info[DotnetPackageInfo.SLN_FILES_TAG].keys():
                pprint(curr_indent, sln_file)
                next_indent = curr_indent + 4
                for proj_file in pkg_info[DotnetPackageInfo.SLN_FILES_TAG][sln_file]:
                    pprint(next_indent, proj_file)
        else:
            for proj_file in pkg_info[DotnetPackageInfo.PROJ_FILES_TAG].keys():
                pprint(curr_indent, proj_file)

                next_indent = curr_indent + 4
                for _key in pkg_info[DotnetPackageInfo.PROJ_FILES_TAG][proj_file].keys():
                    pprint(next_indent, _key)

                    next_indent2 = next_indent + 4
                    if type(pkg_info[DotnetPackageInfo.PROJ_FILES_TAG][proj_file][_key]) == list:
                        for _item in pkg_info[DotnetPackageInfo.PROJ_FILES_TAG][proj_file][_key]:
                            pprint(next_indent2, _item)
                    else:
                        pprint(next_indent2, pkg_info[DotnetPackageInfo.PROJ_FILES_TAG][proj_file][_key])

        curr_indent = curr_indent - 4


if __name__ == '__main__':
    parser = process_cli_args()
    args = vars(parser)

    if args['build_settings'] and args['package']:
        new_pkg_info = verify_and_merge(args['build_settings'], args['package'])
        json.dump(new_pkg_info, sys.stdout)
    elif args['src_file_types']:
        json.dump(DotnetPackageInfo.DOTNET_SRC_FILE_TYPES, sys.stdout)
    elif args['framework_types']:
        json.dump(DotnetPackageInfo.DOTNET_FRAMEWORK_TYPES, sys.stdout)
    elif args['proj_file_types']:
        json.dump(DotnetPackageInfo.DOTNET_PROJ_FILE_TYPES, sys.stdout)
    elif args['package']:
        dpi = DotnetPackageInfo()
        pkg_info = dpi.get_pkg_info(args['package'])

        if args['no_config']:
            pkg_info = dpi.remove_config(pkg_info)

        if args['no_framework']:
            pkg_info = dpi.remove_framework(pkg_info)

        pkg_info = dpi.add_defaults(pkg_info)

        if args['format'] == 'json':
            json.dump(pkg_info, sys.stdout)
        else:
            pretty_print(pkg_info)


