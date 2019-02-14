import sys
import json
from .dotnet_pkg import DotnetPackage
import os.path as osp


def get_build_command(build_file, target_framework=None, build_config=None):
    cmd = ['dotnet', 'build', '"{0}"'.format(build_file)]

    if target_framework:
        cmd.append('--framework')
        cmd.append(target_framework)

    if build_config:
        cmd.append('--configuration')
        cmd.append(build_config)

    return cmd


def get_build_commands(build_settings):

    build_commands = list()

    if DotnetPackage.SLN_FILES_TAG in build_settings and \
       build_settings[DotnetPackage.SLN_FILES_TAG]:

        # There is only one sln_file always
        sln_file = list(build_settings[DotnetPackage.SLN_FILES_TAG].keys())[0]

        # if no proj files listed
        if len(build_settings[DotnetPackage.SLN_FILES_TAG][sln_file]) == 0:
            build_commands.append(get_build_command(sln_file))
        else:
            # When there are one or more proj files 
            projects = build_settings[DotnetPackage.PROJ_FILES_TAG]

            for proj in build_settings[DotnetPackage.SLN_FILES_TAG][sln_file]:

                if projects[proj].get('nobuild', 'false') == 'false':
                    build_commands.append(get_build_command(osp.join(osp.dirname(sln_file), proj),
                                                            projects[proj].get('framework'),
                                                            projects[proj].get('configuration')))

    else:
        # When there are no sln files, but projects
        projects = build_settings[DotnetPackage.PROJ_FILES_TAG]

        for proj in projects.keys():
            if projects[proj].get('nobuild', 'false') == 'false':
                build_commands.append(get_build_command(proj,
                                                        projects[proj].get('framework'),
                                                        projects[proj].get('configuration')))
        
    return {'build_commands': build_commands}


def print_text(build_commands):

    for cmd in build_commands['build_commands']:
        print(' '.join(cmd))


def main(json_str, to_json):

    build_settings = json.loads(json_str)
    build_commands = get_build_commands(build_settings)

    if to_json:
        json.dump(build_commands, sys.stdout)
    else:
        print_text(build_commands)

