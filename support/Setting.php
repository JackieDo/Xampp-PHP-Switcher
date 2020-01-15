<?php

if (! defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

class Setting
{
    protected $settings = [];

    public function __construct()
    {
        $this->reloadSettings();
    }

    public function reloadSettings()
    {
        if (is_file(getenv('XPHP_APP_DIR') . '\settings.ini')) {
            $this->settings = @parse_ini_file(getenv('XPHP_APP_DIR') . '\settings.ini', true);
        }

        return $this;
    }

    public function all()
    {
        return $this->settings;
    }

    public function get($section, $setting, $defaultValue = null)
    {
        if (array_key_exists($section, $this->settings)) {
            $returnValue = (array_key_exists($setting, $this->settings[$section])) ? $this->settings[$section][$setting] : $defaultValue;
        } else {
            $returnValue = $defaultValue;
        }

        return $returnValue;
    }

    public function set($section, $setting, $value)
    {
        if (! array_key_exists($section, $this->settings)) {
            $this->settings[$section] = [];
        }

        $this->settings[$section][$setting] = $value;

        return $this;
    }

    public function save()
    {
        return @create_ini_file(getenv('XPHP_APP_DIR') . '\settings.ini', $this->settings, true);
    }
}
