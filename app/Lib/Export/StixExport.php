<?php

class StixExport
{
    protected $__scripts_dir = APP . 'files/scripts/';
    protected $__end_of_cmd = ' 2>' . APP . 'tmp/logs/exec-errors.log';
    
    private $__tmp_dir = null;
    private $__framing = null;
    private $__stix_file = null;
    private $__tmp_file = null;
    private $__n_attributes = 0;
    private $__filenames = array();

    public $non_restrictive_export = true;

    public function handler($data, $options = array())
    {
        $attributes_count = count($data['Attribute']);
        foreach ($data['Object'] as $_object) {
            $attributes_count += count($_object['Attribute']);
        }
        App::uses('JSONConverterTool', 'Tools');
        $converter = new JSONConverterTool();
        $event = $converter->convert($data);
        if ($this->__n_attributes + $attributes_count < $this->__attributes_limit) {
            ($this->__n_attributes == 0) ? $this->__tmp_file->append($event) : $this->__tmp_file->append(',' . $event);
            $this->__n_attributes += $attributes_count;
        } else {
            if ($attributes_count > $this->__attributes_limit) {
                $randomFileName = $this->generateRandomFileName();
                $tmpFile = new File($this->__tmp_dir . $randomFileName, true, 0644);
                $tmpFile->write($event);
                $tmpFile->close();
                array_push($this->__filenames, $randomFileName);
            } else {
                $this->__tmp_file->append(']}');
                $this->__tmp_file->close();
                $this->__initialize_misp_file();
                $this->__tmp_file->append($event);
                $this->__n_attributes = $attributes_count;
            }
        }
        return '';
    }

    public function header($options = array())
    {
        $framing_cmd = $this->initiate_framing_params($options['returnFormat']);
        $randomFileName = $this->generateRandomFileName();
        $this->__tmp_dir = $this->__scripts_dir . 'tmp/';
        $this->__framing = json_decode(shell_exec($framing_cmd), true);
        $this->__stix_file = new File($this->__tmp_dir . $randomFileName . '.stix');
        $this->__stix_file->write($this->__framing['header']);
        $this->__initialize_misp_file();
        return '';
    }

    public function footer()
    {
        $this->__tmp_file->append(']}');
        $this->__tmp_file->close();
        foreach ($this->__filenames as $filename) {
            $result = $this->__parse_misp_events($filename);
            $decoded = json_decode($result, true);
            if (!isset($decoded['success']) || !$decoded['success']) {
                return '';
            }
            $file = new File($this->__tmp_dir . $filename . '.out');
            $stix_event = $file->read();
            $file->close();
            $file->delete();
            unlink($this->__tmp_dir . $filename);
            $this->__stix_file->append($stix_event . $this->__framing['separator']);
            unset($stix_event);
        }
        $stix_event = $this->__stix_file->read();
        $this->__stix_file->close();
        $this->__stix_file->delete();
        $sep_len = strlen($this->__framing['separator']);
        $stix_event = substr($stix_event, 0, -$sep_len) . $this->__framing['footer'];
        return $stix_event;
    }

    public function separator()
    {
        return '';
    }

    private function __initialize_misp_file()
    {
        $randomFileName = $this->generateRandomFileName();
        $this->__tmp_file = new File($this->__tmp_dir . $randomFileName, true, 0644);
        $this->__tmp_file->write('{"response": [');
        array_push($this->__filenames, $randomFileName);
    }

    public function generateRandomFileName()
    {
        return (new RandomTool())->random_str(false, 12);
    }
}
