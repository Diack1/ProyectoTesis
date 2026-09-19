<?php

return [
    'python' => env('VISION_PYTHON', base_path(PHP_OS_FAMILY === 'Windows' ? 'vision/.venv/Scripts/python.exe' : 'vision/.venv/bin/python')),
    'timeout' => 25,
    // Some Windows development servers discard this inherited OS variable.
    'system_root' => env('VISION_SYSTEM_ROOT', getenv('SystemRoot') ?: getenv('SYSTEMROOT') ?: null),
    'user_profile' => env('VISION_USER_PROFILE', getenv('USERPROFILE') ?: null),
    'session_minutes' => 30,
];
