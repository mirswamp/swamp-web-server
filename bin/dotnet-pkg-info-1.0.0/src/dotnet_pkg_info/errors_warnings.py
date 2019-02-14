from enum import Enum, unique
import json


@unique
class ErrorCodes(Enum):
    SUCCESS = 0
    INVALID_PACKAGE = 1
    INVALID_SLN_FILE = 2
    FILE_NOT_FOUND = 3
    INVALID_PROJECT_FILE = 4
    INVALID_TARGET_FRAMEWORK = 5
    INVALID_BUILD_CONFIGURATION = 6
    INVALID_FILE_EXTENSION = 7
    FILE_PERMISSIONS_ERROR = 8
    GENERIC_ERROR = 9
    REQUIRES_WINDOWS = 10
    

class DotnetPackageError(Exception):

    def __init__(self, code=ErrorCodes.GENERIC_ERROR, file_path=None):
        Exception.__init__(self)
        self.code = code
        self.file_path = file_path

    def to_json(self):
        return {'code': self.code.name, 'file': self.file_path, 'message': str(self)}


class NotADotnetPackageError(DotnetPackageError):

    def __init__(self, file_path):
        DotnetPackageError.__init__(self, ErrorCodes.INVALID_PACKAGE,  file_path)

    def __str__(self):
        return 'No solution or project files found in the directory: {0}'.format(self.file_path)


class InvalidSolutionFile(DotnetPackageError):

    def __init__(self, file_path):
        DotnetPackageError.__init__(self, ErrorCodes.INVALID_SLN_FILE,  file_path)

    def __str__(self):
        return 'Invalid solution file: {0}'.format(self.file_path)


class FileNotFound(DotnetPackageError):

    def __init__(self, file_path):
        DotnetPackageError.__init__(self, ErrorCodes.FILE_NOT_FOUND,  file_path)

    def __str__(self):
        return 'File in the solution file not found: {0}'.format(self.file_path)


class InvalidProjectFile(DotnetPackageError):

    def __init__(self, file_path):
        DotnetPackageError.__init__(self, ErrorCodes.INVALID_PROJECT_FILE,  file_path)

    def __str__(self):
        return 'Invalid project file: {0}'.format(self.file_path)


class InvalidTargetFramework(DotnetPackageError):

    def __init__(self, file_path):
        DotnetPackageError.__init__(self, ErrorCodes.INVALID_TARGET_FRAME_WORK,  file_path)

    def __str__(self):
        return 'Invalid target framework: {0}'.format(self.file_path)


class InvalidBuildConfiguration(DotnetPackageError):

    def __init__(self, file_path):
        DotnetPackageError.__init__(self, ErrorCodes.INVALID_BUILD_CONFIGURATION,  file_path)

    def __str__(self):
        return 'Invalid build configuration: {0}'.format(self.file_path)


class InvalidFileExtension(DotnetPackageError):

    def __init__(self, file_path):
        DotnetPackageError.__init__(self, ErrorCodes.INVALID_FILE_EXTENSION,  file_path)

    def __str__(self):
        return 'Invalid .NET file extension: {0}'.format(self.file_path)


class FilePermissionsError(DotnetPackageError):

    def __init__(self, file_path):
        DotnetPackageError.__init__(self, ErrorCodes.FILE_PERMISSIONS_ERROR,  file_path)

    def __str__(self):
        return 'File permission error: {0}'.format(self.file_path)


class ProjectRequiresWindows(DotnetPackageError):

    def __init__(self, file_path):
        DotnetPackageError.__init__(self, ErrorCodes.REQUIRES_WINDOWS,  file_path)

    def __str__(self):
        return 'Project requires windows to build: {0}'.format(self.file_path)

