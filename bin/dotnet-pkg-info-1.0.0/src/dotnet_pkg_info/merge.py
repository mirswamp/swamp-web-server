import json
import copy
from . import dotnet_pkg
import sys
import pdb


def verify(build_settings, pkg_info):
    pass

def merge(build_settings, pkg_info):

    new_pkg_info = copy.deepcopy(pkg_info)

    # Verify if sln file in build_settings exist in pkg_info
    if 'sln_files' in build_settings and len(build_settings['sln_files'].keys()) > 0:
        sln_file = list(build_settings['sln_files'].keys())[0]

        if sln_file not in pkg_info['sln_files'].keys():
            new_pkg_info['errors'].append(DotnetPackageError(file_path=sln_file).to_json())
        else:
            # Verify if projects in sln array exist
            bs_proj_files = set(build_settings['sln_files'][sln_file])

            if len(bs_proj_files.difference(set(pkg_info['proj_files'].keys()))) > 0:
                new_pkg_info['errors'].extend(DotnetPackageError(file_path=proj_file).to_json()\
                                              for proj_file in bs_proj_files.difference(set(pkg_info['proj_files'].keys())))

    for bs_proj_file in build_settings['proj_files'].keys():

        if bs_proj_file in pkg_info['proj_files'].keys():
            bs_proj_info = build_settings['proj_files'][bs_proj_file]

            if 'framework' in bs_proj_info and \
                    bs_proj_info['framework'] in pkg_info['proj_files'][bs_proj_file]['frameworks']:
                new_pkg_info['proj_files'][bs_proj_file]['framework'] = bs_proj_info['framework']

            if 'configuration' in bs_proj_info and \
                    bs_proj_info['configuration'] in \
                    pkg_info['proj_files'][bs_proj_file]['configurations']:
                new_pkg_info['proj_files'][bs_proj_file]['configuration'] = bs_proj_info['configuration']

            if bs_proj_info.get('nobuild') == "true":
                new_pkg_info['proj_files'][bs_proj_file]['nobuild'] = bs_proj_info['nobuild']


    return new_pkg_info


def main(json_str, package):
    dpkg = dotnet_pkg.main(package)
    pkg_info = dpkg.to_json()
    build_settings = json.loads(json_str)
    verify(build_settings, pkg_info)
    return merge(build_settings, pkg_info)

