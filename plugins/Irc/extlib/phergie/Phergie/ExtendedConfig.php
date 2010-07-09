<?php
class Phergie_Extended_Config extends Phergie_Config {
    /**
     * Incorporates an associative array of settings into the current
     * configuration settings.
     *
     * @param array $array Array of settings
     *
     * @return Phergie_Config Provides a fluent interface
     * @throws Phergie_Config_Exception
     */
    public function readArray($array) {
        $settings = $array;
        if (!is_array($settings)) {
            throw new Phergie_Config_Exception(
                'Parameter is not an array',
                Phergie_Config_Exception::ERR_ARRAY_NOT_RETURNED
            );
        }
        
        $this->settings += $settings;

        return $this;
    }
}
