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
            $source = $this->path.'/../json'.$path.'/'.$language.'.json';
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
        $array = [
            // ISO 639-1 codes
            'aa'      => 'Afar',
            'ab'      => 'Abkhazian',
            'ace'     => 'Achinese',
            'ach'     => 'Acoli',
            'ada'     => 'Adangme',
            'ady'     => 'Adyghe',
            'ae'      => 'Avestan',
            'aeb'     => 'Tunisian Arabic',
            'af'      => 'Afrikaans',
            'afh'     => 'Afrihili',
            'agq'     => 'Aghem',
            'ain'     => 'Ainu',
            'ak'      => 'Akan',
            'akk'     => 'Akkadian',
            'akz'     => 'Alabama',
            'ale'     => 'Aleut',
            'aln'     => 'Gheg Albanian',
            'alt'     => 'Southern Altai',
            'am'      => 'Amharic',
            'an'      => 'Aragonese',
            'ang'     => 'Old English',
            'anp'     => 'Angika',
            'ar'      => 'Arabic',
            'ar_001'  => 'Modern Standard Arabic',
            'arc'     => 'Aramaic',
            'arn'     => 'Mapuche',
            'aro'     => 'Araona',
            'arp'     => 'Arapaho',
            'arq'     => 'Algerian Arabic',
            'arw'     => 'Arawak',
            'ary'     => 'Moroccan Arabic',
            'arz'     => 'Egyptian Arabic',
            'as'      => 'Assamese',
            'asa'     => 'Asu',
            'ase'     => 'American Sign Language',
            'ast'     => 'Asturian',
            'av'      => 'Avaric',
            'avk'     => 'Kotava',
            'awa'     => 'Awadhi',
            'ay'      => 'Aymara',
            'az'      => 'Azerbaijani',
            'azb'     => 'South Azerbaijani',
            'ba'      => 'Bashkir',
            'bal'     => 'Baluchi',
            'ban'     => 'Balinese',
            'bar'     => 'Bavarian',
            'bas'     => 'Basaa',
            'bax'     => 'Bamun',
            'bbc'     => 'Batak Toba',
            'bbj'     => 'Ghomala',
            'be'      => 'Belarusian',
            'bej'     => 'Beja',
            'bem'     => 'Bemba',
            'bew'     => 'Betawi',
            'bez'     => 'Bena',
            'bfd'     => 'Bafut',
            'bfq'     => 'Badaga',
            'bg'      => 'Bulgarian',
            'bho'     => 'Bhojpuri',
            'bi'      => 'Bislama',
            'bik'     => 'Bikol',
            'bin'     => 'Bini',
            'bjn'     => 'Banjar',
            'bkm'     => 'Kom',
            'bla'     => 'Siksika',
            'bm'      => 'Bambara',
            'bn'      => 'Bengali',
            'bo'      => 'Tibetan',
            'bpy'     => 'Bishnupriya',
            'bqi'     => 'Bakhtiari',
            'br'      => 'Breton',
            'bra'     => 'Braj',
            'brh'     => 'Brahui',
            'brx'     => 'Bodo',
            'bs'      => 'Bosnian',
            'bss'     => 'Akoose',
            'bua'     => 'Buriat',
            'bug'     => 'Buginese',
            'bum'     => 'Bulu',
            'byn'     => 'Blin',
            'byv'     => 'Medumba',
            'ca'      => 'Catalan',
            'cad'     => 'Caddo',
            'car'     => 'Carib',
            'cay'     => 'Cayuga',
            'cch'     => 'Atsam',
            'ce'      => 'Chechen',
            'ceb'     => 'Cebuano',
            'cgg'     => 'Chiga',
            'ch'      => 'Chamorro',
            'chb'     => 'Chibcha',
            'chg'     => 'Chagatai',
            'chk'     => 'Chuukese',
            'chm'     => 'Mari',
            'chn'     => 'Chinook Jargon',
            'cho'     => 'Choctaw',
            'chp'     => 'Chipewyan',
            'chr'     => 'Cherokee',
            'chy'     => 'Cheyenne',
            'ckb'     => 'Central Kurdish',
            'co'      => 'Corsican',
            'cop'     => 'Coptic',
            'cps'     => 'Capiznon',
            'cr'      => 'Cree',
            'crh'     => 'Crimean Turkish',
            'cs'      => 'Czech',
            'csb'     => 'Kashubian',
            'cu'      => 'Church Slavic',
            'cv'      => 'Chuvash',
            'cy'      => 'Welsh',
            'da'      => 'Danish',
            'dak'     => 'Dakota',
            'dar'     => 'Dargwa',
            'dav'     => 'Taita',
            'de'      => 'German',
            'de_AT'   => 'Austrian German',
            'de_CH'   => 'Swiss High German',
            'del'     => 'Delaware',
            'den'     => 'Slave',
            'dgr'     => 'Dogrib',
            'din'     => 'Dinka',
            'dje'     => 'Zarma',
            'doi'     => 'Dogri',
            'dsb'     => 'Lower Sorbian',
            'dtp'     => 'Central Dusun',
            'dua'     => 'Duala',
            'dum'     => 'Middle Dutch',
            'dv'      => 'Divehi',
            'dyo'     => 'Jola-Fonyi',
            'dyu'     => 'Dyula',
            'dz'      => 'Dzongkha',
            'dzg'     => 'Dazaga',
            'ebu'     => 'Embu',
            'ee'      => 'Ewe',
            'efi'     => 'Efik',
            'egl'     => 'Emilian',
            'egy'     => 'Ancient Egyptian',
            'eka'     => 'Ekajuk',
            'el'      => 'Greek',
            'elx'     => 'Elamite',
            'en'      => 'English',
            'en_AU'   => 'Australian English',
            'en_CA'   => 'Canadian English',
            'en_GB'   => 'British English',
            'en_US'   => 'American English',
            'enm'     => 'Middle English',
            'eo'      => 'Esperanto',
            'es'      => 'Spanish',
            'es_419'  => 'Latin American Spanish',
            'es_ES'   => 'European Spanish',
            'es_MX'   => 'Mexican Spanish',
            'esu'     => 'Central Yupik',
            'et'      => 'Estonian',
            'eu'      => 'Basque',
            'ewo'     => 'Ewondo',
            'ext'     => 'Extremaduran',
            'fa'      => 'Persian',
            'fan'     => 'Fang',
            'fat'     => 'Fanti',
            'ff'      => 'Fulah',
            'fi'      => 'Finnish',
            'fil'     => 'Filipino',
            'fit'     => 'Tornedalen Finnish',
            'fj'      => 'Fijian',
            'fo'      => 'Faroese',
            'fon'     => 'Fon',
            'fr'      => 'French',
            'fr_CA'   => 'Canadian French',
            'fr_CH'   => 'Swiss French',
            'frc'     => 'Cajun French',
            'frm'     => 'Middle French',
            'fro'     => 'Old French',
            'frp'     => 'Arpitan',
            'frr'     => 'Northern Frisian',
            'frs'     => 'Eastern Frisian',
            'fur'     => 'Friulian',
            'fy'      => 'Western Frisian',
            'ga'      => 'Irish',
            'gaa'     => 'Ga',
            'gag'     => 'Gagauz',
            'gan'     => 'Gan Chinese',
            'gay'     => 'Gayo',
            'gba'     => 'Gbaya',
            'gbz'     => 'Zoroastrian Dari',
            'gd'      => 'Scottish Gaelic',
            'gez'     => 'Geez',
            'gil'     => 'Gilbertese',
            'gl'      => 'Galician',
            'glk'     => 'Gilaki',
            'gmh'     => 'Middle High German',
            'gn'      => 'Guarani',
            'goh'     => 'Old High German',
            'gom'     => 'Goan Konkani',
            'gon'     => 'Gondi',
            'gor'     => 'Gorontalo',
            'got'     => 'Gothic',
            'grb'     => 'Grebo',
            'grc'     => 'Ancient Greek',
            'gsw'     => 'Swiss German',
            'gu'      => 'Gujarati',
            'guc'     => 'Wayuu',
            'gur'     => 'Frafra',
            'guz'     => 'Gusii',
            'gv'      => 'Manx',
            'gwi'     => 'Gwichʼin',
            'ha'      => 'Hausa',
            'hai'     => 'Haida',
            'hak'     => 'Hakka Chinese',
            'haw'     => 'Hawaiian',
            'he'      => 'Hebrew',
            'hi'      => 'Hindi',
            'hif'     => 'Fiji Hindi',
            'hil'     => 'Hiligaynon',
            'hit'     => 'Hittite',
            'hmn'     => 'Hmong',
            'ho'      => 'Hiri Motu',
            'hr'      => 'Croatian',
            'hsb'     => 'Upper Sorbian',
            'hsn'     => 'Xiang Chinese',
            'ht'      => 'Haitian',
            'hu'      => 'Hungarian',
            'hup'     => 'Hupa',
            'hy'      => 'Armenian',
            'hz'      => 'Herero',
            'ia'      => 'Interlingua',
            'iba'     => 'Iban',
            'ibb'     => 'Ibibio',
            'id'      => 'Indonesian',
            'ie'      => 'Interlingue',
            'ig'      => 'Igbo',
            'ii'      => 'Sichuan Yi',
            'ik'      => 'Inupiaq',
            'ilo'     => 'Iloko',
            'inh'     => 'Ingush',
            'io'      => 'Ido',
            'is'      => 'Icelandic',
            'it'      => 'Italian',
            'iu'      => 'Inuktitut',
            'izh'     => 'Ingrian',
            'ja'      => 'Japanese',
            'jam'     => 'Jamaican Creole English',
            'jbo'     => 'Lojban',
            'jgo'     => 'Ngomba',
            'jmc'     => 'Machame',
            'jpr'     => 'Judeo-Persian',
            'jrb'     => 'Judeo-Arabic',
            'jut'     => 'Jutish',
            'jv'      => 'Javanese',
            'ka'      => 'Georgian',
            'kaa'     => 'Kara-Kalpak',
            'kab'     => 'Kabyle',
            'kac'     => 'Kachin',
            'kaj'     => 'Jju',
            'kam'     => 'Kamba',
            'kaw'     => 'Kawi',
            'kbd'     => 'Kabardian',
            'kbl'     => 'Kanembu',
            'kcg'     => 'Tyap',
            'kde'     => 'Makonde',
            'kea'     => 'Kabuverdianu',
            'ken'     => 'Kenyang',
            'kfo'     => 'Koro',
            'kg'      => 'Kongo',
            'kgp'     => 'Kaingang',
            'kha'     => 'Khasi',
            'kho'     => 'Khotanese',
            'khq'     => 'Koyra Chiini',
            'khw'     => 'Khowar',
            'ki'      => 'Kikuyu',
            'kiu'     => 'Kirmanjki',
            'kj'      => 'Kuanyama',
            'kk'      => 'Kazakh',
            'kkj'     => 'Kako',
            'kl'      => 'Kalaallisut',
            'kln'     => 'Kalenjin',
            'km'      => 'Khmer',
            'kmb'     => 'Kimbundu',
            'kn'      => 'Kannada',
            'ko'      => 'Korean',
            'koi'     => 'Komi-Permyak',
            'kok'     => 'Konkani',
            'kos'     => 'Kosraean',
            'kpe'     => 'Kpelle',
            'kr'      => 'Kanuri',
            'krc'     => 'Karachay-Balkar',
            'kri'     => 'Krio',
            'krj'     => 'Kinaray-a',
            'krl'     => 'Karelian',
            'kru'     => 'Kurukh',
            'ks'      => 'Kashmiri',
            'ksb'     => 'Shambala',
            'ksf'     => 'Bafia',
            'ksh'     => 'Colognian',
            'ku'      => 'Kurdish',
            'kum'     => 'Kumyk',
            'kut'     => 'Kutenai',
            'kv'      => 'Komi',
            'kw'      => 'Cornish',
            'ky'      => 'Kyrgyz',
            'la'      => 'Latin',
            'lad'     => 'Ladino',
            'lag'     => 'Langi',
            'lah'     => 'Lahnda',
            'lam'     => 'Lamba',
            'lb'      => 'Luxembourgish',
            'lez'     => 'Lezghian',
            'lfn'     => 'Lingua Franca Nova',
            'lg'      => 'Ganda',
            'li'      => 'Limburgish',
            'lij'     => 'Ligurian',
            'liv'     => 'Livonian',
            'lkt'     => 'Lakota',
            'lmo'     => 'Lombard',
            'ln'      => 'Lingala',
            'lo'      => 'Lao',
            'lol'     => 'Mongo',
            'loz'     => 'Lozi',
            'lt'      => 'Lithuanian',
            'ltg'     => 'Latgalian',
            'lu'      => 'Luba-Katanga',
            'lua'     => 'Luba-Lulua',
            'lui'     => 'Luiseno',
            'lun'     => 'Lunda',
            'luo'     => 'Luo',
            'lus'     => 'Mizo',
            'luy'     => 'Luyia',
            'lv'      => 'Latvian',
            'lzh'     => 'Literary Chinese',
            'lzz'     => 'Laz',
            'mad'     => 'Madurese',
            'maf'     => 'Mafa',
            'mag'     => 'Magahi',
            'mai'     => 'Maithili',
            'mak'     => 'Makasar',
            'man'     => 'Mandingo',
            'mas'     => 'Masai',
            'mde'     => 'Maba',
            'mdf'     => 'Moksha',
            'mdr'     => 'Mandar',
            'men'     => 'Mende',
            'mer'     => 'Meru',
            'mfe'     => 'Morisyen',
            'mg'      => 'Malagasy',
            'mga'     => 'Middle Irish',
            'mgh'     => 'Makhuwa-Meetto',
            'mgo'     => 'Metaʼ',
            'mh'      => 'Marshallese',
            'mi'      => 'Maori',
            'mic'     => 'Micmac',
            'min'     => 'Minangkabau',
            'mk'      => 'Macedonian',
            'ml'      => 'Malayalam',
            'mn'      => 'Mongolian',
            'mnc'     => 'Manchu',
            'mni'     => 'Manipuri',
            'moh'     => 'Mohawk',
            'mos'     => 'Mossi',
            'mr'      => 'Marathi',
            'mrj'     => 'Western Mari',
            'ms'      => 'Malay',
            'mt'      => 'Maltese',
            'mua'     => 'Mundang',
            'mus'     => 'Creek',
            'mwl'     => 'Mirandese',
            'mwr'     => 'Marwari',
            'mwv'     => 'Mentawai',
            'my'      => 'Burmese',
            'mye'     => 'Myene',
            'myv'     => 'Erzya',
            'mzn'     => 'Mazanderani',
            'na'      => 'Nauru',
            'nan'     => 'Min Nan Chinese',
            'nap'     => 'Neapolitan',
            'naq'     => 'Nama',
            'nb'      => 'Norwegian Bokmål',
            'nd'      => 'North Ndebele',
            'nds'     => 'Low German',
            'ne'      => 'Nepali',
            'new'     => 'Newari',
            'ng'      => 'Ndonga',
            'nia'     => 'Nias',
            'niu'     => 'Niuean',
            'njo'     => 'Ao Naga',
            'nl'      => 'Dutch',
            'nl_BE'   => 'Flemish',
            'nmg'     => 'Kwasio',
            'nn'      => 'Norwegian Nynorsk',
            'nnh'     => 'Ngiemboon',
            'no'      => 'Norwegian',
            'nog'     => 'Nogai',
            'non'     => 'Old Norse',
            'nov'     => 'Novial',
            'nqo'     => 'NʼKo',
            'nr'      => 'South Ndebele',
            'nso'     => 'Northern Sotho',
            'nus'     => 'Nuer',
            'nv'      => 'Navajo',
            'nwc'     => 'Classical Newari',
            'ny'      => 'Nyanja',
            'nym'     => 'Nyamwezi',
            'nyn'     => 'Nyankole',
            'nyo'     => 'Nyoro',
            'nzi'     => 'Nzima',
            'oc'      => 'Occitan',
            'oj'      => 'Ojibwa',
            'om'      => 'Oromo',
            'or'      => 'Oriya',
            'os'      => 'Ossetic',
            'osa'     => 'Osage',
            'ota'     => 'Ottoman Turkish',
            'pa'      => 'Punjabi',
            'pag'     => 'Pangasinan',
            'pal'     => 'Pahlavi',
            'pam'     => 'Pampanga',
            'pap'     => 'Papiamento',
            'pau'     => 'Palauan',
            'pcd'     => 'Picard',
            'pdc'     => 'Pennsylvania German',
            'pdt'     => 'Plautdietsch',
            'peo'     => 'Old Persian',
            'pfl'     => 'Palatine German',
            'phn'     => 'Phoenician',
            'pi'      => 'Pali',
            'pl'      => 'Polish',
            'pms'     => 'Piedmontese',
            'pnt'     => 'Pontic',
            'pon'     => 'Pohnpeian',
            'prg'     => 'Prussian',
            'pro'     => 'Old Provençal',
            'ps'      => 'Pashto',
            'pt'      => 'Portuguese',
            'pt_BR'   => 'Brazilian Portuguese',
            'pt_PT'   => 'European Portuguese',
            'qu'      => 'Quechua',
            'quc'     => 'Kʼicheʼ',
            'qug'     => 'Chimborazo Highland Quichua',
            'raj'     => 'Rajasthani',
            'rap'     => 'Rapanui',
            'rar'     => 'Rarotongan',
            'rgn'     => 'Romagnol',
            'rif'     => 'Riffian',
            'rm'      => 'Romansh',
            'rn'      => 'Rundi',
            'ro'      => 'Romanian',
            'ro_MD'   => 'Moldavian',
            'rof'     => 'Rombo',
            'rom'     => 'Romany',
            'root'    => 'Root',
            'rtm'     => 'Rotuman',
            'ru'      => 'Russian',
            'rue'     => 'Rusyn',
            'rug'     => 'Roviana',
            'rup'     => 'Aromanian',
            'rw'      => 'Kinyarwanda',
            'rwk'     => 'Rwa',
            'sa'      => 'Sanskrit',
            'sad'     => 'Sandawe',
            'sah'     => 'Sakha',
            'sam'     => 'Samaritan Aramaic',
            'saq'     => 'Samburu',
            'sas'     => 'Sasak',
            'sat'     => 'Santali',
            'saz'     => 'Saurashtra',
            'sba'     => 'Ngambay',
            'sbp'     => 'Sangu',
            'sc'      => 'Sardinian',
            'scn'     => 'Sicilian',
            'sco'     => 'Scots',
            'sd'      => 'Sindhi',
            'sdc'     => 'Sassarese Sardinian',
            'se'      => 'Northern Sami',
            'see'     => 'Seneca',
            'seh'     => 'Sena',
            'sei'     => 'Seri',
            'sel'     => 'Selkup',
            'ses'     => 'Koyraboro Senni',
            'sg'      => 'Sango',
            'sga'     => 'Old Irish',
            'sgs'     => 'Samogitian',
            'sh'      => 'Serbo-Croatian',
            'shi'     => 'Tachelhit',
            'shn'     => 'Shan',
            'shu'     => 'Chadian Arabic',
            'si'      => 'Sinhala',
            'sid'     => 'Sidamo',
            'sk'      => 'Slovak',
            'sl'      => 'Slovenian',
            'sli'     => 'Lower Silesian',
            'sly'     => 'Selayar',
            'sm'      => 'Samoan',
            'sma'     => 'Southern Sami',
            'smj'     => 'Lule Sami',
            'smn'     => 'Inari Sami',
            'sms'     => 'Skolt Sami',
            'sn'      => 'Shona',
            'snk'     => 'Soninke',
            'so'      => 'Somali',
            'sog'     => 'Sogdien',
            'sq'      => 'Albanian',
            'sr'      => 'Serbian',
            'srn'     => 'Sranan Tongo',
            'srr'     => 'Serer',
            'ss'      => 'Swati',
            'ssy'     => 'Saho',
            'st'      => 'Southern Sotho',
            'stq'     => 'Saterland Frisian',
            'su'      => 'Sundanese',
            'suk'     => 'Sukuma',
            'sus'     => 'Susu',
            'sux'     => 'Sumerian',
            'sv'      => 'Swedish',
            'sw'      => 'Swahili',
            'swb'     => 'Comorian',
            'swc'     => 'Congo Swahili',
            'syc'     => 'Classical Syriac',
            'syr'     => 'Syriac',
            'szl'     => 'Silesian',
            'ta'      => 'Tamil',
            'tcy'     => 'Tulu',
            'te'      => 'Telugu',
            'tem'     => 'Timne',
            'teo'     => 'Teso',
            'ter'     => 'Tereno',
            'tet'     => 'Tetum',
            'tg'      => 'Tajik',
            'th'      => 'Thai',
            'ti'      => 'Tigrinya',
            'tig'     => 'Tigre',
            'tiv'     => 'Tiv',
            'tk'      => 'Turkmen',
            'tkl'     => 'Tokelau',
            'tkr'     => 'Tsakhur',
            'tl'      => 'Tagalog',
            'tlh'     => 'Klingon',
            'tli'     => 'Tlingit',
            'tly'     => 'Talysh',
            'tmh'     => 'Tamashek',
            'tn'      => 'Tswana',
            'to'      => 'Tongan',
            'tog'     => 'Nyasa Tonga',
            'tpi'     => 'Tok Pisin',
            'tr'      => 'Turkish',
            'tru'     => 'Turoyo',
            'trv'     => 'Taroko',
            'ts'      => 'Tsonga',
            'tsd'     => 'Tsakonian',
            'tsi'     => 'Tsimshian',
            'tt'      => 'Tatar',
            'ttt'     => 'Muslim Tat',
            'tum'     => 'Tumbuka',
            'tvl'     => 'Tuvalu',
            'tw'      => 'Twi',
            'twq'     => 'Tasawaq',
            'ty'      => 'Tahitian',
            'tyv'     => 'Tuvinian',
            'tzm'     => 'Central Atlas Tamazight',
            'udm'     => 'Udmurt',
            'ug'      => 'Uyghur',
            'uga'     => 'Ugaritic',
            'uk'      => 'Ukrainian',
            'umb'     => 'Umbundu',
            'und'     => 'Unknown Language',
            'ur'      => 'Urdu',
            'uz'      => 'Uzbek',
            'vai'     => 'Vai',
            've'      => 'Venda',
            'vec'     => 'Venetian',
            'vep'     => 'Veps',
            'vi'      => 'Vietnamese',
            'vls'     => 'West Flemish',
            'vmf'     => 'Main-Franconian',
            'vo'      => 'Volapük',
            'vot'     => 'Votic',
            'vro'     => 'Võro',
            'vun'     => 'Vunjo',
            'wa'      => 'Walloon',
            'wae'     => 'Walser',
            'wal'     => 'Wolaytta',
            'war'     => 'Waray',
            'was'     => 'Washo',
            'wbp'     => 'Warlpiri',
            'wo'      => 'Wolof',
            'wuu'     => 'Wu Chinese',
            'xal'     => 'Kalmyk',
            'xh'      => 'Xhosa',
            'xmf'     => 'Mingrelian',
            'xog'     => 'Soga',
            'yao'     => 'Yao',
            'yap'     => 'Yapese',
            'yav'     => 'Yangben',
            'ybb'     => 'Yemba',
            'yi'      => 'Yiddish',
            'yo'      => 'Yoruba',
            'yrl'     => 'Nheengatu',
            'yue'     => 'Cantonese',
            'za'      => 'Zhuang',
            'zap'     => 'Zapotec',
            'zbl'     => 'Blissymbols',
            'zea'     => 'Zeelandic',
            'zen'     => 'Zenaga',
            'zgh'     => 'Standard Moroccan Tamazight',
            'zh'      => 'Chinese',
            'zh_Hans' => 'Simplified Chinese',
            'zh_Hant' => 'Traditional Chinese',
            'zu'      => 'Zulu',
            'zun'     => 'Zuni',
            'zxx'     => 'No linguistic content',
            'zza'     => 'Zaza',
        ];

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
