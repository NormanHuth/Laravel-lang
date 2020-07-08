<?php

namespace caouecs\LaravelLang;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LaravelLang extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:lang {--languages=} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish language files for Laravel';

    /**
     * @var true|false
     */
    protected $force = false;

    /**
     * @var string
     */
    protected $path = __DIR__;

    /**
     * @var array
     */
    protected $languages = [];

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @var array
     */
    protected $infos = [];

    /**
     * @var array
     */
    protected $warns = [];

    /**
     * @var array
     */
    protected $filter = ['_alt'];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $languages   = $this->option('languages');
        $this->force = $this->option('force') ? true : false;

        if(!$languages) {
            $this->getLanguages();

            $this->line('');
            $this->info(__('You must choose at one or more languages that you want to publish.'));
            $this->info(__('With comma separation you can publish multiple languages. Or enter the word "all" to publish all languages.'));
            $languages = $this->ask(__('Which languages would you like to publish? (Cancel with CTRL+X)'));

            if (!$languages) {
                $this->error(__('The action was canceled. No language was published.'));

                return 0;
            }
        }

        $languages = array_unique(explode(',', $languages));

        $error = 0;
        foreach ($languages as $language) {
            if (!in_array($language, $this->allLanguages()) && $language!='all') {
                $this->error(__('Error. The language ":miss" was not found.', ['miss' => $language]));
                $error++;
            }
        }

        if (in_array('all', $languages)) {
            $languages = $this->allLanguages();
        }

        if (count($languages) == count($this->allLanguages())) {
            $all = $this->ask('Warning. You\'ve selected all languages. Do you really want to publish all languages? [y/yes]');
            if (strtolower($all)!='y' && strtolower($all)!='yes') {
                $this->error(__('The action was canceled. No language was published.'));

                return 0;
            }
        }


        if ($error) {
            $this->error('▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲');
            $this->error(__('No language was published. Please check your request.'));

            return 0;
        }

        foreach ($languages as $language) {
            $path = '/';
            if ($language=='de' || $language=='de-CH') {
                $alt = $this->askGerman($language);
                if (strtolower($alt)=='du') {
                    $path = $path.'_alt/';
                }
            }

            if (!is_dir(resource_path('lang/'.$language))) {
                mkdir(resource_path('lang/'.$language), 0775);
            }

            $files =[
                'auth',
                'pagination',
                'passwords',
                'validation',
            ];

            // PHP files
            foreach ($files as $file) {
                $source = $this->path.$path.$language.'/'.$file.'.php';
                $target = resource_path('lang/'.$language.'/'.$file.'.php');

                if ($this->askOverwrite($source, $target)) {
                    copy($source, $target);
                    $this->infos[] = __('The file :file has been added.', ['file' => $target]);
                }
            }

            // Json file
            $source = $this->path.'/../json'.$path.$language.'.json';
            $target = resource_path('lang/'.$language.'.json');

            if ($this->askOverwrite($source, $target)) {
                copy($source, $target);
                $this->infos[] = __('The file :file has been added.', ['file' => $target]);
            }
        }

        $this->readyMessage(__('Action executed'));

        foreach ($this->infos as $info) {
            $this->info($info);
        }

        foreach ($this->warns as $warn) {
            $this->warn($warn);
        }

        foreach ($this->errors as $error) {
            $this->error($error);
        }

        return 1;
    }

    protected function readyMessage($string)
    {
        $string = '#   '.$string.'   #';

        $int = strlen($string);
        $row = '';
        for ($i = 1; $i <= $int; $i++) {
            $row.= '#';
        }

        $this->line('');
        $this->line($row);
        $this->line($string);
        $this->line($row);
    }

    protected function askOverwrite($source, $target) {
        if (!file_exists($source)) {
            $this->errors[] = __('The file :file was not found.', ['file' => $source]);

            return 0;
        }
        if (!file_exists($target) || $this->force) {
            return 1;
        }
        $answer = $this->ask(__('The file :file already exists. Do you want to overwrite it? [y/n]', ['file' => $target]));
        if (strtolower($answer)=='y' || strtolower($answer)=='yes') {
            return 1;
        }
        if (strtolower($answer)=='n' || strtolower($answer)=='no') {
            $this->warns[] = __('The file :file was not copied because it already exists.', ['file' => $target]);
            return 0;
        }
        return $this->askOverwrite($source, $target);
    }

    protected function askGerman($language)
    {
        $answer = $this->ask(__('If you prefer to use the informal "du" form for language :lang instead of the formal "Sie" form, then enter "du". For the formal "Sie" form, leave the entry empty.', ['lang'=> '['.$language.']'.' ('.$this->codeWord($language).')']));
        if (strtolower($answer)=='du' || $answer=='') {
            return $answer;
        }

        return $this->askGerman($language);
    }

    /**
     * @return array
     */
    protected function allLanguages()
    {
        $array = [];
        foreach (glob($this->path.'/*', GLOB_ONLYDIR) as $folder) {
            if (!in_array(basename($folder), $this->filter)) {
                $array[] = basename($folder);
            }
        }
        return $array;
    }

    /**
     * @return bool
     */
    protected function getLanguages()
    {
        if (!is_dir($this->path)) {
            $this->error(__('The folder could not be resolved. Please copy files manually.'));

            return false;
        }

        foreach (glob($this->path.'/*', GLOB_ONLYDIR) as $folder)
        {
            if (!in_array(basename($folder), $this->filter)) {
                $this->line(basename($folder).' => '.$this->codeWord(basename($folder)));
            }
        }
        return true;
    }

    /**
     * @param $code
     * @return string
     */
    protected function codeWord($code)
    {
        $array = include __DIR__.'/languages.php';

        $word = str_replace('-', '_', $code);
        $show = isset($array[$word]) ? $array[$word]:'';
        if (!$show) {
            $split = explode('_', $word);
            if (isset($split[1]) && isset($array[$split[0]])) {
                $show = $array[$split[0]].' - '.$split[1];
            }
        }

        if (!$show) {
            $show = '<'.__('unknown').'>';
        }

        return $show;
    }
}
