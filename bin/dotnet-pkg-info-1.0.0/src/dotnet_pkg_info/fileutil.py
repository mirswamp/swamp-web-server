import os
import os.path as osp
import glob
from collections import namedtuple

from . import utillib


FileFilters = namedtuple('FileFilters', ['exclude_dirs', 'exclude_files',
                                         'include_dirs', 'include_files'])


def os_path_join(basepath, subdir):
    if subdir.startswith('/'):
        return osp.normpath(osp.join(basepath, subdir[1:]))
    else:
        return osp.normpath(osp.join(basepath, subdir))


def glob_glob(path, pattern):
    return glob.glob(os_path_join(path, pattern))


def expand_patterns(root_dir, pattern_list):
    for pattern in pattern_list:
        if '**' in pattern:
            head, _, tail = pattern.partition('**')
            tail = tail[1:] if tail.startswith('/') else tail
            if tail in ['', '*.*', '*']:
                yield glob_glob(root_dir, head)
            else:
                for dirpath, _, _ in os.walk(os_path_join(root_dir, head)):
                    yield glob_glob(dirpath, tail)
        else:
            yield glob_glob(root_dir, pattern)


def get_file_filters(root_dir, patterns):
    '''Returns an FileFilters object'''

    # patterns is expected to be a list or filepath
    if isinstance(patterns, str):
        with open(patterns) as fobj:
            patterns = [p for p in fobj]
    elif patterns is None:
        patterns = []

    patterns = {p.strip().strip('\n') for p in patterns
                if p and not p.isspace() and not p.strip().startswith('#')}

    ex_dir_list = set()
    ex_file_list = set()

    for fileset in expand_patterns(root_dir,
                                   (p for p in patterns
                                    if not p.startswith('!'))):
        if fileset:
            for _file in fileset:
                if osp.isdir(_file):
                    ex_dir_list.add(osp.normpath(_file))
                else:
                    ex_file_list.add(osp.normpath(_file))

    in_dir_list = set()
    in_file_list = set()

    for fileset in expand_patterns(root_dir,
                                   (p[1:] for p in patterns
                                    if p.startswith('!'))):
        if fileset:
            for _file in fileset:
                if osp.isdir(_file):
                    in_dir_list.add(osp.normpath(_file))
                else:
                    in_file_list.add(osp.normpath(_file))

    return FileFilters(ex_dir_list, ex_file_list, in_dir_list, in_file_list)


def filter_out(root_dir, file_filters, file_extentions):
    '''
    This is a generator function.
    os.walk with directories in file_filters.exclude_dirs and
    file_filters.exclude_files and hidden (begin with .) are ignored
    '''

    def is_dirpath_in(dirpath, dir_list):
        return any(dirpath.startswith(path) for path in dir_list) \
            if dir_list else False

    hidden_dir_list = []

    for dirpath, _, filenames in os.walk(root_dir):

        if osp.basename(dirpath).startswith('.'):
            hidden_dir_list.append(dirpath)
        elif not (is_dirpath_in(osp.join(dirpath, ''), file_filters.exclude_dirs) or
                  is_dirpath_in(osp.join(dirpath, ''), hidden_dir_list)):
            filepaths = {osp.normpath(osp.join(dirpath, _file))
                         for _file in filenames
                         if not _file.startswith('.') and
                         (osp.splitext(_file)[1] in file_extentions)}
            filepaths = filepaths.difference(file_filters.exclude_files)
            if filepaths:
                for _file in filepaths:
                    yield _file


def filter_in(file_filters, file_extentions):
    '''
    This is a generator function.
    '''

    for include_dir in file_filters.include_dirs:
        for dirpath, _, filenames in os.walk(include_dir):
            for _file in filenames:
                if osp.splitext(_file)[1] in file_extentions:
                    yield osp.join(dirpath, _file)

    for _file in file_filters.include_files:
        if osp.isfile(_file) and \
           osp.splitext(_file)[1] in file_extentions:
            yield _file


def get_file_list(root_dir, patterns, file_extentions):
    '''
    In the root_dir path, applies patterns and returns the list of files matching the extensions in file_extentions
    '''

    file_filters = get_file_filters(root_dir, patterns)

    file_list = list()
    file_list.extend(filter_out(root_dir, file_filters, file_extentions))
    file_list.extend(filter_in(file_filters, file_extentions))

    return file_list


def filter_file_list(file_list, root_dir, patterns):
    ''' 
    Given a set of files (file_list), excludes the files matching the patterns and returns a new file list
    '''

    file_filters = get_file_filters(root_dir, patterns)

    new_file_list = set(file_list).difference(file_filters.exclude_files)

    def is_file_in(filepath, dir_list):
        return any(filepath.startswith(osp.join(path, ''))
                   for path in dir_list) if dir_list else False

    new_file_list = new_file_list.difference({_file for _file in new_file_list
                                              if is_file_in(_file, file_filters.exclude_dirs)})

    return list(new_file_list)

#########################


def split_file_list_old(file_list, max_size, sep=' '):
    '''
    Split file_list
    '''

    def split(llist, file_list, max_size):
        if len(sep.join(file_list)) > max_size:
            split(llist, file_list[0:int(len(file_list) / 2)], max_size)
            split(llist, file_list[int(len(file_list) / 2):], max_size)
        else:
            llist.append(file_list)

    list_of_lists = list()
    split(list_of_lists, file_list, max_size)
    return list_of_lists


def chunk_file_list(file_list, max_size, sep=' '):
    '''
    Split file_list
    '''

    sub_list = list()
    list_size = 0
    len_sep = len(sep)

    for _file in file_list:
        if (list_size + len_sep + len(_file) < max_size):
            sub_list.append(_file)
            list_size = list_size + len_sep + len(_file)
        else:
            yield sub_list
            sub_list.clear()
            sub_list.append(_file)
            list_size = len(_file)

    if len(sub_list):
        yield sub_list
