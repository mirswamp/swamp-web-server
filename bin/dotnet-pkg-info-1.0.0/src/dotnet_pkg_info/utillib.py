import os
import os.path as osp
import subprocess
import sys
import datetime
import time
import re
import shlex
import uuid
import logging
import zipfile
import pdb


if 'PermissionError' in __builtins__:
    PermissionException = PermissionError
else:
    class PermissionException(OSError):
        pass

if 'FileNotFoundError' in __builtins__:
    FileNotFoundException = FileNotFoundError
else:
    class FileNotFoundException(OSError):
        pass

if 'NotADirectoryError' in __builtins__:
    NotADirectoryException = NotADirectoryError
else:
    class NotADirectoryException(OSError):
        pass

if 'IsADirectoryError' in __builtins__:
    IsADirectoryException = IsADirectoryError
else:
    class IsADirectoryException(OSError):
        pass


class UnpackArchiveError(Exception):

    def __init__(self, filename):
        Exception.__init__(self)
        self.filename = filename
        self.exit_code = 5

    def __str__(self):
        return "Unpacking archive '{0}' failed".format(self.filename)


def datetime_iso8601():
    return datetime.datetime.isoformat(datetime.datetime.now())


def posix_epoch():
    return str(time.time())


def _unpack_archive_xz(archive, dirpath):

    xz_proc = subprocess.Popen(['xz', '--decompress', '--stdout', archive],
                               stdout=subprocess.PIPE,
                               stderr=sys.stderr)

    tar_proc = subprocess.Popen(['tar', '-x'],
                                stdin=xz_proc.stdout,
                                stdout=sys.stdout,
                                stderr=sys.stderr,
                                cwd=dirpath)

    xz_proc.stdout.close()
    tar_proc.communicate()

    return tar_proc.returncode


def unpack_archive(archive, dirpath, createdir=True):
    '''
    Unarchives/Extracts the file \'archive\' in the directory \'dirpath\'.
    Expects \'dirpath\' to be already present.
    Throws FileNotFoundException and NotADirectoryException if
    archive or dirpath not found
    ValueError if archive format is not supported.
    '''

    if not osp.isfile(archive):
        raise FileNotFoundException(archive)

    if not osp.isdir(dirpath):
        if createdir:
            os.makedirs(dirpath)
        else:
            raise NotADirectoryException(dirpath)

    archive = osp.abspath(archive)
    dirpath = osp.abspath(dirpath)

    cmd_template_dict = {'.tar.gz': 'tar -x -z -f {0}',
                         '.tgz': 'tar -x -z -f {0}',
                         '.tar.Z': 'tar -x -Z -f {0}',
                         '.tar.bz2': 'tar -x -j -f {0}',
                         '.tar': 'tar -x -f {0}',
                         '.zip': 'unzip -qq -o {0}',
                         '.jar': 'unzip -qq -o {0}',
                         '.war': 'unzip -qq -o {0}',
                         '.ear': 'unzip -qq -o {0}',
                         '.phar': 'phar extract -f {0}',
                         #'whl': 'wheel unpack --dest {1} {0}'
                         '.whl': 'unzip -qq -o {0}',
    }
    
    if archive.endswith('.zip'):
        with zipfile.ZipFile(archive, "r") as zip_ref:
            zip_ref.extractall(dirpath)
            return 0
    elif any((archive.endswith(ext) for ext in cmd_template_dict)):
        cmd_template = [cmd_template_dict[ext] for ext in cmd_template_dict if archive.endswith(ext)][0]
        cmd = cmd_template.format(archive, dirpath)
        return run_cmd(cmd, cwd=dirpath, description='UNPACK ARCHIVE')[0]
    elif archive.endswith('.tar.xz'):
        return _unpack_archive_xz(archive, dirpath)
    else:
        raise ValueError('Format not supported')


def run_cmd(cmd,
            outfile=sys.stdout,
            errfile=sys.stderr,
            infile=None,
            cwd='.',
            shell=False,
            env=None,
            description='UNKNOWN'):
    '''argument cmd should be a list'''

    def openfile(filename, mode):
        return open(filename, mode) if(isinstance(filename, str)) else filename

    def closefile(filename, fileobj):
        if isinstance(filename, str):
            fileobj.close()

    out = openfile(outfile, 'w')
    err = openfile(errfile, 'w')
    inn = openfile(infile, 'r')

    if isinstance(cmd, str):
        shell = True

    environ = dict(os.environ) if env is None else env
    environ['PWD'] = osp.abspath(cwd)

    try:
        logging.info('%s COMMAND %s', description, cmd)
        logging.info('%s WORKING DIR %s', description, environ['PWD'])

        popen = subprocess.Popen(cmd,
                                 stdout=out,
                                 stderr=err,
                                 stdin=inn,
                                 shell=shell,
                                 cwd=environ['PWD'],
                                 env=environ)
        popen.wait()
        exit_code = popen.returncode
    except (subprocess.CalledProcessError, FileNotFoundException) as exception:
        if hasattr(exception, 'returncode'):
            exit_code = err.returncode
        else:
            exit_code = 1
    finally:
        logging.info('%s EXIT CODE %s', description, exit_code)
        logging.info('%s ENVIRONMENT %s', description, environ)

        closefile(outfile, out)
        closefile(errfile, err)
        closefile(infile, inn)

    return (exit_code, environ)


def get_cmd_output(cmd, cwd=None):
    try:
        output = subprocess.check_output(cmd)
        return output.decode('utf-8').strip()
    except subprocess.CalledProcessError:
        return None
                

def get_cpu_type():
    '64-bit or 32-bit'
    try:
        output = subprocess.check_output(['getconf', 'LONG_BIT'])
        return int(output.decode('utf-8').strip())
    except subprocess.CalledProcessError:
        return None


def max_cmd_size():
    # expr `getconf ARG_MAX` - `env|wc -c` - `env|wc -l` \* 4 - 2048
    #arg_max = subprocess.check_output(['getconf', 'ARG_MAX'])
    #arg_max = int(arg_max.decode(encoding='utf-8').strip())
    # if arg_max > 131072:

    if platform() == 'Windows_NT':
        return 32767
    else:
        arg_max = 131072
        env_len = len(''.join([str(k) + ' ' + str(os.environ[k]) for k in os.environ.keys()]))
        env_num = len(os.environ.keys())  # for null ptr
        arg_max = arg_max - env_len - env_num * 4 - 2048  # extra caution
        return arg_max


def platform():
    if 'VMPLATNAME' in os.environ:
        return os.environ['VMPLATNAME']
    elif hasattr(os, 'uname'):
        platname = os.uname()
        return platname[3] if(isinstance(platname, tuple)) else platname.version
    else:
        return os.getenv('OS')


def write_to_file(filename, obj):
    '''write a dictionary or list object to a file'''

    with open(filename, 'w') as fobj:

        if isinstance(obj, dict):
            for key in obj.keys():
                print('{0}={1}'.format(key, obj[key]), file=fobj)

        if isinstance(obj, list):
            for entity in obj:
                print(entity, file=fobj)


# PARAM_REGEX = re.compile(r'<(?P<name>[a-zA-Z][a-zA-Z_-]*)(?:[%](?P<sep>[^>]+))?>')
PARAM_REGEX = re.compile(r'<(?P<name>[a-zA-Z][a-zA-Z0-9]*(?:[_-]?[a-zA-Z0-9]+)*)(?:[%](?P<sep>[^>]+))?>')


def string_substitute(string_template, symbol_table):
    '''Substitues environment variables and
    config parameters in the string.
    quotes the string and returns it'''

    new_str = string_template
    for match in PARAM_REGEX.finditer(string_template):
        name = match.groupdict()['name']
        sep = match.groupdict()['sep']

        if name in symbol_table:
            value = symbol_table[name]
            if not isinstance(value, str):
                if sep is None:
                    value = value[0]
                else:
                    value = sep.join(value)
        else:
            value = ''

        f = '<{0}>' if sep is None else '<{0}%{1}>'
        new_str = new_str.replace(f.format(match.groupdict()['name'],
                                           match.groupdict()['sep']),
                                  value, 1)

    return osp.expandvars(new_str)


def expandvar(var, kwargs):
    return string_substitute(var, kwargs)


def rmfile(filename):
    if osp.isfile(filename):
        os.remove(filename)


# Copied from Python3.3 Standard Libary shlex.py for portability in Python3.2
_find_unsafe = re.compile(r'[^\w@%+=:,./-]', re.ASCII).search


def _quote(s):
    """Return a shell-escaped version of the string *s*."""
    if not s:
        return "''"
    if _find_unsafe(s) is None:
        return s

    # use single quotes, and put single quotes into double quotes
    # the string $'b is then quoted as '$'"'"'b'
    return "'" + s.replace("'", "'\"'\"'") + "'"


def quote_str(s):
    if hasattr(shlex, 'quote'):
        return shlex.quote(s)
    else:
        return _quote(s)


def get_uuid():
    return str(uuid.uuid4())


def ordered_list(_list):

    _set = set()
    new_list = list()

    for item in _list:
        if item not in _set:
            _set.add(item)
            new_list.append(item)

    return new_list
