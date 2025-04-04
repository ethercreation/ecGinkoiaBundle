<?php
namespace bundles\ecGinkoiaBundle\src;

use Pimcore\Model\WebsiteSetting;
use Pimcore\Model\DataObject\Data\ObjectMetadata;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Data\ElementMetadata;
use bundles\ecGinkoiaBundle\src\ecTimer;
use bundles\ecGinkoiaBundle\src\connector;
use bundles\ecGinkoiaBundle\src\ecCollect;
use bundles\ecMiddleBundle\Services\DbFile;
use bundles\ecMiddleBundle\Services\Outils;
use bundles\ecShopifyBundle\Services\ShopifyApiClient;
use Carbon\Carbon;
use Exception;
use phpseclib3\Net\SFTP;
use Pimcore\Model\DataObject\Product;
use bundles\ecMiddleBundle\Controller\ecMiddleController;

class ecProduct extends FrontendController
{
    /**
     * @var bundles\ecGinkoiaBundle\src\ecCustomer
     */
    private static $instance;
    /**
     * @var bundles\ecGinkoiaBundle\src\ecTimer
     */
    public $timer;
    /**
     * @var bundles\ecGinkoiaBundle\src\ecCollect
     */
    private $collect;

    public $config;
    public $trans_stream;
    
    public $catalog_name = 'catalogue_ginkoia';
    public $categorie_name = 'categorie_ginkoia';
    public $article_categorie_name = 'article_categorie_ginkoia';
    public $prix_name = 'prix_ginkoia';
    public $genre_name = 'genre_ginkoia';
    public $taille_name = 'taille_ginkoia';
    public $pack_stock_name = 'pack_stk_ginkoia';
    public $prix_stock = 'prix_stk_ginkoia';
    public $ean13_name = 'ean13_ginkoia';
    public $couleur_name = 'couleur_ginkoia';
    
    public $stock_name = 'stock_ginkoia';
    
    public $catalog_price_name = 'catalogue_price_ginkoia';
    public $prix_price_name = 'prix_price_ginkoia';
    public $oc_name = 'oc_ginkoia';

    public $artWeb = 'INIT_ARTWEB_4.TXT';
    public $nomenclature = '*NOMENCLATURE_2.TXT';
    public $artNomenk = '*ARTNOMENK_2.TXT';
    public $prix = '*PRIX_2.TXT';
    public $genres = '*GENRE_2.TXT';
    public $grillesTailles = '*TAILLES_2.TXT';
    public $stock = '*STOCK_4.TXT';
    public $oc = '*OC_2.TXT';
    public $cb_fourn = '*CB_FOURN_3.TXT';
    public $couleur_stat = '*COULEUR_STAT_2.TXT';


    public $tva = 20;

    public $tab_cat = [
        'active' => 'ETAT_DATA',
        'reference' => 'CODE_CHRONO',
        'crossid' => 'CODE_MODELE',
        'id' => 'CODE_MODELE',
        'ean13' => 'CODE_EAN',
        'upc' => '',
        'isbn' => '',
        'quantity' => '',
        'width' => '',
        'height' => '',
        'depth' => '',
        'weight' => 'POIDS',
        'wholesale_price' => 'PUMP',
        'price' => 'PXVTE',
        'id_tax' => 'TVA',
        'id_category_default' => 'CODE_NK',
        'name' => 'PRODUIT',
        'description' => 'WEB_DETAIL',
        'description_short' => 'WEB_DETAIL',
        'meta_description' => '',
        'meta_title' => '',
        'link_rewrite' => '',
        
        'genre' => 'GENRE',
        'manufacturer' => 'MARQUE',
        'active' => 'ETAT_DATA',
        'decl_reference' => 'CODE_ARTICLE',
        'couleur' => 'COULEUR',
	    'archive' => 'ARCHIVER',
        'composition' => 'WEB_COMPOSITION',
    ];
    public $tab_feat = [
        'Saison' => 'COLLECTION',
        'Chrono' => 'CODE_CHRONO',
        'Reference' => 'CODE_FOURN',
    ];
    public $tab_attr = [
        'crossid' => 'CODE_ARTICLE',
        'id' => 'CODE_ARTICLE',
        'code_regroupement' => 'CODE_MODELE',
        'supplier_reference' => 'CODE_ARTICLE',
        'reference' => 'CODE_FOURN',
        'ean13' => 'CODE_EAN',
        'weight' => 'POIDS',
        'upc' => '',
        'price' => 'PUMP',
        'pmvc' => 'PXVTE',
        'rate' => 'TVA',

        'couleur' => 'COULEUR',
        'taille' => 'TAILLE',
    ];
    public $tab_stock = [
        'crossid' => 'CODE_ARTICLE',
        'reference' => 'CODE_ARTICLE',
        'location' => 'MAG_ID',
        'stock' => 'QTE_STOCK',
        'price' => 'PUMP',
        'pmvc' => 'PXVTE',
    ];
    
    


    /**
     * @return self
     */
    public function __construct()
    {
        $this->timer = ecTimer::get();
        $this->collect = ecCollect::get();

        $connector = new connector();
        $diffusion = $connector->getDiffusion();
        
        $this->config = [
            'diffusion_auto' => json_decode(Outils::getConfigByName($diffusion, 'diffusion_automatique_ginkoia'), true),
            'ftp_hote' => $diffusion->getFtp_hote(),
            'ftp_port' => $diffusion->getFtp_port(),
            'ftp_login' => $diffusion->getFtp_login(),
            'ftp_pass' => $diffusion->getFtp_password(),
            'ftp_chemin' => rtrim($diffusion->getFtp_chemin(), '/').'/',
            'export_ftp' => $diffusion->getExport_ftp(),
            'ftp_sftp' => $diffusion->getFtp_sftp(),
            'forceSendOrder' => Outils::getConfigByName($diffusion, 'forceSendOrder'),

            'use_nomenk' => Outils::getConfigByName($diffusion, 'ecGinkoiaUseNomenk'),
            'use_artnomenk' => Outils::getConfigByName($diffusion, 'ecGinkoiaUseArtNomenk'),
            'use_cbfourn' => Outils::getConfigByName($diffusion, 'ecGinkoiaUseCbFourn'),
            'use_couleur_stat' => Outils::getConfigByName($diffusion, 'ecGinkoiaUseCouleurStat'),
            'use_oc' => Outils::getConfigByName($diffusion, 'ecGinkoiaUseOC'),
            'which_product_reference' => Outils::getConfigByName($diffusion, 'ecGinkoiaProductReference'),
            'which_combination_reference' => Outils::getConfigByName($diffusion, 'ecGinkoiaCombinationReference'),
        ];
        
        foreach (range(1, 5) as $i) {
            $name = Outils::getConfigByName($diffusion, 'ecGinkoiaClassement'.$i) ?? '';
            if (!empty($name)) {
                $this->tab_feat[$name] = 'CLASSEMENT'.$i;
            }
        }

        $this->tab_cat['reference'] = $this->config['which_product_reference'] ?: $this->tab_cat['reference'];
        $this->tab_attr['reference'] = $this->config['which_combination_reference'] ?: $this->tab_attr['reference'];

        return $this;
    }

    /**
     * @return self
     */
    public static function get()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** 
     * @return string
     */
    public function __toString()
    {
        return json_encode($this, JSON_PRETTY_PRINT);
    }
    

    public function __destruct()
    {
        // $this->output();
    }


    /**
     * SFTP
     * 164.132.207.107
     * 22
     * ubuntu
     * e7hercrea7!on5
     * /var/www/html2/lerouquin/modules/eciginkoiav/files
     */

    /**
     * FTP
     * ethercreation.io
     * 21
     * 7cf05d-2
     * OfAeQUCF
     * /
     */




    public function cronSyncGinkoia(array $params)
    {
        $connector = new connector();
        $diffusion = $connector->getDiffusion();

        $host = $this->config['ftp_hote'];
        $pass = $this->config['ftp_pass'];
        $login = $this->config['ftp_login'];
        $port = $this->config['ftp_port'];
        $path = $this->config['ftp_chemin'];
        
        $localImportPath = dirname(__FILE__).'/../files/import/';
        $localExportPath = dirname(__FILE__).'/../files/export/';
        $remoteImportPath = $path.'import/';
        $remoteExportPath = $path.'export/';

        if (false === $this->config['export_ftp']) {
            return true;
        }

        if ($this->config['ftp_sftp']) { // Passage en SFTP
            // Récupération des fichiers d'import
            $sftp_stream = new SFTP($host, $port);
            if (false === $sftp_stream->login($login, $pass)) {
                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - SyncGinkoia : Connexion impossible au SFTP ' . $host, 3);
                return false;
            }
            $files = $sftp_stream->rawlist($remoteImportPath);
            $sftp_stream->chdir($remoteImportPath);
            foreach ($files as $fileInfo) {
                if (false === strpos($fileInfo['filename'], '.TXT')) {
                    continue; // Passe les dossier et les fichier qui ne finisse pas par TXT
                }

                if (file_exists($localImportPath.$fileInfo['filename'])) {
                    if (filemtime($localImportPath.$fileInfo['filename']) >= $fileInfo['mtime']) {
                        continue; // Le fichier distant n'a pas été modifié
                    }
                }
                
                if (false === $sftp_stream->get($remoteImportPath.$fileInfo['filename'], $localImportPath.$fileInfo['filename'])) {
                    Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - SyncGinkoia : Récupération du fichier en erreur : ' . $fileInfo['filename'], 3);
                    return false;
                }
            }

            // Dépot des commandes
            $files = glob($localExportPath . '*.xml');
            foreach ($files as $filePath) {
                $name = explode('/', $filePath);
                $fileName = end($name);

                if (false === $sftp_stream->put($remoteExportPath.$fileName, $filePath, SFTP::SOURCE_LOCAL_FILE)) {
                    Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - SyncGinkoia : Impossible de poser le fichier ' . $fileName, 3);
                    return false;
                }
                @unlink($filePath);
            }
        } else { // Passage en FTP
            // Connexion au serveur FTP
            if (false === $this->ftpLogin()) {
                return false;
            }

            // Récupération des fichiers
            $files = $this->ftpGetList($remoteImportPath.'*', 'rawlist');
            foreach ($files as $fileInfo) {
                if (false === strpos($fileInfo['name'], '.TXT')) {
                    continue; // Passe les dossier et les fichier qui ne finisse pas par TXT
                }
                $name = explode('/', $fileInfo['name']);
                $fileName = end($name);

                if (file_exists($localImportPath.$fileName)) {
                    if (filemtime($localImportPath.$fileName) >= $fileInfo['mtime']) {
                        continue; // Le fichier distant n'a pas été modifié
                    }
                }

                if (false === $this->ftpGet($localImportPath.$fileName, $fileInfo['name'])) {
                    return false;
                }
            }

            // Dépot des commandes
            $files = glob($localExportPath . '*.xml');
            foreach ($files as $filePath) {
                $name = explode('/', $filePath);
                $fileName = end($name);

                if (false === $this->ftpSend($filePath, $remoteExportPath)) {
                    return false;
                }
                @unlink($filePath);
            }
        }

        return true;
    }
    
    public function ftpLogin()
    {
        // get parameters from configuration
        $ftp_address = $this->config['ftp_hote'] ?? '';
        $ftp_port = $this->config['ftp_port'] ?? 21;
        $ftp_login = $this->config['ftp_login'] ?? '';
        $ftp_pswd = $this->config['ftp_pass'] ?? '';

        if (!$ftp_login || !$ftp_pswd) {
            return false;
        }

        if (false === ($ftp_stream = ftp_connect($ftp_address, $ftp_port))) {
            Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - SyncGinkoia : Connexion impossible au FTP ' . $ftp_address, 3);
            return false;
        }
        if (false === @ftp_login($ftp_stream, $ftp_login, $ftp_pswd)) {
            Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - SyncGinkoia : Authentification impossible au FTP ' . $ftp_address, 3);
            return false;
        }
        ftp_pasv($ftp_stream, true);
        ftp_set_option($ftp_stream, FTP_USEPASVADDRESS, false);

        $this->trans_stream = $ftp_stream;

        return true;
    }

    public function ftpLogoff()
    {
        if (is_null($this->trans_stream)) {
            return true;
        }

        ftp_close($this->trans_stream);
        $this->trans_stream = null;

        return true;
    }
    
    public function ftpGetList($path = '/', $method = 'nlist')
    {
        if (is_null($this->trans_stream)) {
            return false;
        }
        
        switch ($method) {
            case 'rawlist':
                $files = ftp_rawlist($this->trans_stream, $path);
                if (is_array($files)) {
                    $rawlist = $files;
                    $files = [];        
                    foreach ($rawlist as $child) {
                        $chunks = preg_split("/\s+/", $child);
                        list($item['rights'], $item['number'], $item['user'], $item['group'], $item['size'], $item['month'], $item['day'], $item['time']) = $chunks;
                        $item['type'] = ($chunks[0][0] === 'd') ? 'directory' : 'file';
                        $item['name'] = preg_replace('/^([^\s]+\s+){8}/', '', $child); // get name by cutting the data
                        // $item['mtime'] = strtotime(implode('-', [date('Y'), $item['month'], $item['day']]).' '.implode(':', [$item['time'], '00']));
                        $item['mtime'] = ftp_mdtm($this->trans_stream, $item['name']);
                        $files[$item['name']] = $item;
                    }
                }
                break;
            case 'mlsd':
                $files = ftp_mlsd($this->trans_stream, $path);
                break;
            default:
                $files = ftp_nlist($this->trans_stream, $path);
        }

        if (false === $files) {
            Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - SyncGinkoia : Impossible de scanner le dossier ' . $path, 3);
            return false;
        }

        return $files;
    }

    public function ftpGet($localPath, $remotePath, $mode = FTP_BINARY)
    {
        if (is_null($this->trans_stream)) {
            return false;
        }

        if (false === ftp_get($this->trans_stream, $localPath, $remotePath, $mode)) {
            Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - SyncGinkoia : Impossible de récupérer le fichier ' . $remotePath, 3);
            return false;
        }

        return true;
    }

    public function ftpSend($filename, $targetPath = '/', $mode = FTP_BINARY)
    {
        if (is_null($this->trans_stream)) {
            return false;
        }

        $name = basename($filename);

        if (false === ftp_put($this->trans_stream, $targetPath . $name, $filename, $mode)) {
            Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - SyncGinkoia : Impossible de poser le fichier ' . $filename, 3);
            return false;
        }

        return true;
    }

    public function cronGetFile(array $params)
    {
        $cron = $params['nbParent'] ?? 'manualTest';
        $nbCron = $params['nbCron'] ?? 0;
        $stopTime = $params['stopTime'] ?? (time() + 15);
        
        $dir = dirname(__FILE__).'/../files/';
        $dirDepot = dirname(__FILE__).'/../files/import/';

        $lst = [
            [
                'name' => $this->catalog_name,
                'erpName' => $this->artWeb,
                'use' => true,
            ],
            [
                'name' => $this->categorie_name,
                'erpName' => $this->nomenclature,
                'use' => $this->config['use_nomenk'],
            ],
            [
                'name' => $this->article_categorie_name,
                'erpName' => $this->artNomenk,
                'use' => $this->config['use_artnomenk'],
            ],
            [
                'name' => $this->prix_name,
                'erpName' => $this->prix,
                'use' => true,
            ],
            [
                'name' => $this->genre_name,
                'erpName' => $this->genres,
                'use' => true,
            ],
            [
                'name' => $this->taille_name,
                'erpName' => $this->grillesTailles,
                'use' => true,
            ],
            [
                'name' => $this->ean13_name,
                'erpName' => $this->cb_fourn,
                'use' => $this->config['use_cbfourn'],
            ],
            [
                'name' => $this->couleur_name,
                'erpName' => $this->couleur_stat,
                'use' => $this->config['use_couleur_stat'],
            ],
            // [
            //     'name' => $this->stock_name,
            //     'erpName' => $this->stock,
            //     'use' => true,
            // ],
            // [
            //     'name' => $this->oc_name,
            //     'erpName' => $this->oc,
            //     'use' => true,
            // ],
        ];

        $fileName = $lst[$nbCron]['name'];
        $fileInfos = $lst[$nbCron]['erpName'];
        $timer_key = __FUNCTION__ . '_' . $fileName;
        $this->timer->start($timer_key);
        
        if (!isset($lst[$nbCron])) {
            $this->timer->stop($timer_key);
            return false;
        }

        if (!$lst[$nbCron]['use']) {
            if (isset($lst[($nbCron + 1)])) {
                $this->timer->stop($timer_key);
                return ($nbCron + 1);
            }

            $this->timer->stop($timer_key);
            return true;
        }

        $listGinkoFile = glob($dirDepot.$fileInfos);
        if (false !== strpos($fileInfos, 'STOCK_*')) {
            @unlink($dir.$fileName.'.tmp');
        }

        // Récupération du fichier INIT
        $initFileTime = 0;
        foreach ($listGinkoFile as $ginkoFile) {
            if (false === strpos($ginkoFile, 'INIT')) { // Not INIT
                continue;
            }
            if (0 != $initFileTime) { // Cumul INIT
                file_put_contents($dir.$fileName.'.tmp', file_get_contents($ginkoFile), FILE_APPEND);
            }
            if (0 == $initFileTime) {
                $initFileTime = filemtime($ginkoFile);
                if (!file_exists($dir.$fileName.'.tmp') || (filemtime($dir.$fileName.'.tmp') < $initFileTime)) { // Nouvelle INIT
                    copy($ginkoFile, $dir.$fileName.'.tmp');
                }
            }
        }
        
        // Ajout des fichiers différentiel
        foreach ($listGinkoFile as $ginkoFile) {
            if ($initFileTime < filemtime($ginkoFile)) { // Nouveau Delta
                file_put_contents($dir.$fileName.'.tmp', file_get_contents($ginkoFile), FILE_APPEND);
            }
        }

        if (!$initFileTime && (false === strpos($fileInfos, 'STOCK_*'))) {
            $this->timer->stop($timer_key);
            Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Aucun fichier INIT_'.$fileInfos.' trouvé dans le dossier !', 3);
            return '(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Aucun fichier INIT_'.$fileInfos.' trouvé dans le dossier !';
            return false;
        }
        if(!is_dir($dir.'archive')) {
            mkdir($dir.'archive');
        }
        copy($dir.$fileName.'.tmp', $dir.'archive/'.$fileName.date('Y-m-d-H-i-s').'.csv');
        
        // Transformation
        $this->timer->start('buildFromArray_'.$fileName);
        $ret = DbFile::buildFromCsv($dir.$fileName.'.tmp', 1, ';', '"', '\\', "\n", 'ISO-8859-1');
        $this->timer->stop('buildFromArray_'.$fileName);
        if (!is_numeric($ret)) {
            $this->timer->stop($timer_key);
            Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur lors de la création du DbFile '.$fileName.' : '.var_export($ret, true), 3);
            return '(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur lors de la création du DbFile '.$fileName.' : '.var_export($ret, true);
            return false;
        }

        if (isset($lst[($nbCron + 1)])) {
            $this->timer->stop($timer_key);
            return ($nbCron + 1);
        }

        $listCleanArchive = glob($dir.'archive/*.csv');
        $dateToClean = time() - (7 * 24 * 60 * 60); // 7 jours; 24 heures; 60 minutes; 60 secondes
        foreach ($listCleanArchive as $cleanFile) {
            if ($dateToClean > filemtime($cleanFile)) {
                unlink($cleanFile);
            }
        }

        $this->timer->stop($timer_key);
        return true;
    }
    
    public function cronGetFileStock(array $params)
    {
        $cron = $params['nbParent'] ?? 'manualTest';
        $nbCron = $params['nbCron'] ?? 0;
        $stopTime = $params['stopTime'] ?? (time() + 15);
        
        $dir = dirname(__FILE__).'/../files/';
        $dirDepot = dirname(__FILE__).'/../files/import/';

        $lst = [
            [
                'name' => $this->stock_name,
                'erpName' => $this->stock,
                'use' => true,
            ],
        ];

        $timer_key = __FUNCTION__ . '_' . $nbCron;
        $this->timer->start($timer_key);
        
        if (!isset($lst[$nbCron])) {
            $this->timer->stop($timer_key);
            return false;
        }
        $fileName = $lst[$nbCron]['name'];
        $fileInfos = $lst[$nbCron]['erpName'];

        if (!$lst[$nbCron]['use']) {
            if (isset($lst[($nbCron + 1)])) {
                $this->timer->stop($timer_key);
                return ($nbCron + 1);
            }

            $this->timer->stop($timer_key);
            return true;
        }

        $listGinkoFile = glob($dirDepot.$fileInfos);
        if (false !== strpos($fileInfos, 'STOCK_*')) {
            @unlink($dir.$fileName.'.tmp');
        }

        // Récupération du fichier INIT
        $initFileTime = 0;
        foreach ($listGinkoFile as $ginkoFile) {
            if (false === strpos($ginkoFile, 'INIT')) { // Not INIT
                continue;
            }
            if (0 != $initFileTime) { // Cumul INIT
                file_put_contents($dir.$fileName.'.tmp', file_get_contents($ginkoFile), FILE_APPEND);
            }
            if (0 == $initFileTime) {
                $initFileTime = filemtime($ginkoFile);
                if (!file_exists($dir.$fileName.'.tmp') || (filemtime($dir.$fileName.'.tmp') < $initFileTime)) { // Nouvelle INIT
                    copy($ginkoFile, $dir.$fileName.'.tmp');
                }
            }
        }
        
        // Ajout des fichiers différentiel
        foreach ($listGinkoFile as $ginkoFile) {
            if ($initFileTime < filemtime($ginkoFile)) { // Nouveau Delta
                file_put_contents($dir.$fileName.'.tmp', file_get_contents($ginkoFile), FILE_APPEND);
            }
        }

        if (!$initFileTime && (false === strpos($fileInfos, 'STOCK_*'))) {
            $this->timer->stop($timer_key);
            Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Aucun fichier INIT_'.$fileInfos.' trouvé dans le dossier !', 3);
            return '(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Aucun fichier INIT_'.$fileInfos.' trouvé dans le dossier !';
            return false;
        }
        if(!is_dir($dir.'archive')) {
            mkdir($dir.'archive');
        }
        copy($dir.$fileName.'.tmp', $dir.'archive/'.$fileName.date('Y-m-d-H-i-s').'.csv');
        
        // Transformation
        $this->timer->start('buildFromArray_'.$fileName);
        $ret = DbFile::buildFromCsv($dir.$fileName.'.tmp', 1, ';', '"', '\\', "\n", 'ISO-8859-1');
        $this->timer->stop('buildFromArray_'.$fileName);
        if (!is_numeric($ret)) {
            $this->timer->stop($timer_key);
            Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur lors de la création du DbFile '.$fileName.' : '.var_export($ret, true), 3);
            return '(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur lors de la création du DbFile '.$fileName.' : '.var_export($ret, true);
            return false;
        }

        if (isset($lst[($nbCron + 1)])) {
            $this->timer->stop($timer_key);
            return ($nbCron + 1);
        }

        $listCleanArchive = glob($dir.'archive/*.csv');
        $dateToClean = time() - (7 * 24 * 60 * 60); // 7 jours; 24 heures; 60 minutes; 60 secondes
        foreach ($listCleanArchive as $cleanFile) {
            if ($dateToClean > filemtime($cleanFile)) {
                unlink($cleanFile);
            }
        }

        $this->timer->stop($timer_key);
        return true;
    }

    public function cronGetFilePrice(array $params)
    {
        $cron = $params['nbParent'] ?? 'manualTest';
        $nbCron = $params['nbCron'] ?? 0;
        $stopTime = $params['stopTime'] ?? (time() + 15);
        
        $dir = dirname(__FILE__).'/../files/';
        $dirDepot = dirname(__FILE__).'/../files/import/';

        $lst = [
            [
                'name' => $this->catalog_price_name,
                'erpName' => $this->artWeb,
                'use' => true,
            ],
            [
                'name' => $this->prix_price_name,
                'erpName' => $this->prix,
                'use' => true,
            ],
            [
                'name' => $this->oc_name,
                'erpName' => $this->oc,
                'use' => $this->config['use_oc'],
            ],
        ];

        $fileName = $lst[$nbCron]['name'];
        $fileInfos = $lst[$nbCron]['erpName'];
        $timer_key = __FUNCTION__ . '_' . $fileName;
        $this->timer->start($timer_key);
        
        if (!isset($lst[$nbCron])) {
            $this->timer->stop($timer_key);
            return false;
        }

        if (!$lst[$nbCron]['use']) {
            if (isset($lst[($nbCron + 1)])) {
                $this->timer->stop($timer_key);
                return ($nbCron + 1);
            }

            $this->timer->stop($timer_key);
            return true;
        }

        $listGinkoFile = glob($dirDepot.$fileInfos);
        if (false !== strpos($fileInfos, 'STOCK_*')) {
            @unlink($dir.$fileName.'.tmp');
        }

        // Récupération du fichier INIT
        $initFileTime = 0;
        foreach ($listGinkoFile as $ginkoFile) {
            if (false === strpos($ginkoFile, 'INIT')) { // Not INIT
                continue;
            }
            if (0 != $initFileTime) { // Cumul INIT
                file_put_contents($dir.$fileName.'.tmp', file_get_contents($ginkoFile), FILE_APPEND);
            }
            if (0 == $initFileTime) {
                $initFileTime = filemtime($ginkoFile);
                if (!file_exists($dir.$fileName.'.tmp') || (filemtime($dir.$fileName.'.tmp') < $initFileTime)) { // Nouvelle INIT
                    copy($ginkoFile, $dir.$fileName.'.tmp');
                }
            }
        }
        
        // Ajout des fichiers différentiel
        foreach ($listGinkoFile as $ginkoFile) {
            if ($initFileTime < filemtime($ginkoFile)) { // Nouveau Delta
                file_put_contents($dir.$fileName.'.tmp', file_get_contents($ginkoFile), FILE_APPEND);
            }
        }

        if (!$initFileTime && (false === strpos($fileInfos, 'STOCK_*'))) {
            $this->timer->stop($timer_key);
            Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Aucun fichier INIT_'.$fileInfos.' trouvé dans le dossier !', 3);
            return '(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Aucun fichier INIT_'.$fileInfos.' trouvé dans le dossier !';
            return false;
        }
        if(!is_dir($dir.'archive')) {
            mkdir($dir.'archive');
        }
        copy($dir.$fileName.'.tmp', $dir.'archive/'.$fileName.date('Y-m-d-H-i-s').'.csv');
        
        // Transformation
        $this->timer->start('buildFromArray_'.$fileName);
        $ret = DbFile::buildFromCsv($dir.$fileName.'.tmp', 1, ';', '"', '\\', "\n", 'ISO-8859-1');
        $this->timer->stop('buildFromArray_'.$fileName);
        if (!is_numeric($ret)) {
            $this->timer->stop($timer_key);
            Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur lors de la création du DbFile '.$fileName.' : '.var_export($ret, true), 3);
            return '(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur lors de la création du DbFile '.$fileName.' : '.var_export($ret, true);
            return false;
        }

        if (isset($lst[($nbCron + 1)])) {
            $this->timer->stop($timer_key);
            return ($nbCron + 1);
        }

        $listCleanArchive = glob($dir.'archive/*.csv');
        $dateToClean = time() - (7 * 24 * 60 * 60); // 7 jours; 24 heures; 60 minutes; 60 secondes
        foreach ($listCleanArchive as $cleanFile) {
            if ($dateToClean > filemtime($cleanFile)) {
                unlink($cleanFile);
            }
        }

        $this->timer->stop($timer_key);
        return true;
    }

    /**
     * MAJ Catalogue
     */

    public function cronFillCatalog(array $params)
    {
        $cron = $params['nbParent'] ?? 'manualTest';
        $nbCron = $params['nbCron'] ?? 1;
        // $nbCron = 255;
        $stopTime = $params['stopTime'] ?? (time() + 15);
        $connector = new connector();
        $diffusion = $connector->getDiffusion();

        $this->timer->start('DbFile_open');
        $json = new DbFile($this->catalog_name);

        $tab_json = [];
        $tab_json[$this->genre_name] = new DbFile($this->genre_name);
        $tab_json[$this->taille_name] = new DbFile($this->taille_name);
        $tab_json[$this->prix_name] = new DbFile($this->prix_name);
        
        if ($this->config['use_nomenk']) {
            $tab_json[$this->categorie_name] = new DbFile($this->categorie_name);
        }
        if ($this->config['use_artnomenk']) {
            $tab_json[$this->article_categorie_name] = new DbFile($this->article_categorie_name);
        }
        if ($this->config['use_cbfourn']) {
            $tab_json[$this->ean13_name] = new DbFile($this->ean13_name);
        }
        if ($this->config['use_couleur_stat']) {
            $tab_json[$this->couleur_name] = new DbFile($this->couleur_name);
        }
        $this->timer->stop('DbFile_open');
        
        // reset des indexes des json, sinon on risque de travailler avec des indexes obsolètes
        if (!$nbCron) {
            $this->timer->start('DbFile_deleteIndex');
            foreach ($tab_json as $objBigjson) {
                $objBigjson->deleteIndex();
            }
            $json->deleteIndex();
            $this->timer->stop('DbFile_deleteIndex');

            // Force buildIndex
            $this->timer->start('DbFile_buildIndex');
            $json->buildIndex(['i', 'CODE_MODELE', 'CODE_ARTICLE', 'TGF_ID']);
            $tab_json[$this->prix_name]->buildIndex('CODE_ARTICLE');
            $tab_json[$this->taille_name]->buildIndex('TGF_ID');
            $tab_json[$this->genre_name]->buildIndex('GRE_ID');
            
            if ($this->config['use_couleur_stat']) {
                $tab_json[$this->couleur_name]->buildIndex('GCS_ID');
            }
            if ($this->config['use_cbfourn']) {
                $tab_json[$this->ean13_name]->buildIndex('CODE_ARTICLE');
            }
            if ($this->config['use_artnomenk']) {
                $tab_json[$this->article_categorie_name]->buildIndex('CODE_MODEL');
            }
            if ($this->config['use_nomenk']) {
                $tab_json[$this->categorie_name]->buildIndex('ID_GINKO');
            }
            $this->timer->stop('DbFile_buildIndex');
        }

        $json->go($nbCron);
        while ($item = $json->read()) {
            if ((time() > $stopTime) && ($json->currentLine > $nbCron)) {
                break;
            }

            $tab_product =  $categList = [];
            foreach ($this->tab_cat as $field => $tag) {
                if ($tag && array_key_exists($tag, $item)) {
                    $tab_product[$field] = trim($item[$tag]);
                }
            }

            if (0 == $tab_product['active']) {
                continue;
            }

            if ('24925951' != $item['CODE_MODELE']) {
                // continue;
            }
            
            // Taxe *
            if (true) {
                $rate = is_numeric($tab_product['id_tax'] ?? '') ? $tab_product['id_tax'] : $this->tva;
                $tab_tax = [
                    'crossid' => $tab_product['id_tax'] ?? $this->tva,
                    'id' => $tab_product['id_tax'] ?? $this->tva,
                    'active' => true,
                    'name' => $tab_product['id_tax'] ?? $this->tva,
                    'rate' => $rate,
                    'catCompta' => '',
                ];
           
                if ($idPim = Outils::getObjectByCrossId($tab_tax['crossid'], 'tax', $diffusion)) {
                    $tab_product['id_tax'] = $idPim;
                } else {
                    $tax = json_decode(json_encode($tab_tax));
                    $this->timer->start('putCreateTax');
                    $time = microtime(true);
                    // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START putCreateTax', 2);
                    $tab_product['id_tax'] = Outils::putCreateTax($tax, $diffusion, 1);
                    // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END putCreateTax : Time = '.(microtime(true) - $time), 2);
                    $this->timer->stop('putCreateTax');
                }
            }

            /**
             * Déclinaisons
             */
            $decliList = [];
            $this->timer->start('DbFile_select_decli');
            $listDecl = DbFile::arJsonDecodeRecur(Outils::query('SELECT * FROM `eci_midle_file_catalogue_ginkoia` WHERE CODE_MODELE = "'.$tab_product['crossid'].'"'), true);
            $this->timer->stop('DbFile_select_decli');

            if ($json->currentLine != $listDecl[0]['i']) {
                continue;
            }

            $automatchProduct = false;
            $default_on = true;
            foreach ($listDecl as $item_decl) {
                $tab_product_combi = [];
                foreach ($this->tab_attr as $field => $tag) {
                    if ($tag && array_key_exists($tag, $item_decl)) {
                        $tab_product_combi[$field] = $item_decl[$tag];
                    }
                }

                // Reference
                $tab_product_combi['reference'] = (string) $tab_product_combi['reference'];

                // Prices
                $this->timer->start('DbFile_select_prix');
                $linePrice = DbFile::arJsonDecodeRecur(Outils::query('SELECT * FROM `eci_midle_file_prix_ginkoia` WHERE CODE_ARTICLE = "'.$tab_product_combi['crossid'].'"'), true);
                $this->timer->stop('DbFile_select_prix');
                $tab_product_combi['price'] = $tab_product_combi['pmvc'] = 0;
                if (isset($linePrice[0][$this->tab_attr['price']])) {
                    $tab_product_combi['price'] = $linePrice[0][$this->tab_attr['price']];
                    $tab_product_combi['pmvc'] = $linePrice[0][$this->tab_attr['pmvc']];
                    $tab_prices = $this->calculatePrices($tab_product_combi['price'], $tab_product_combi['pmvc'], $rate);
                    $tab_product_combi['price'] = $tab_prices['price'];
                    $tab_product_combi['pmvc'] = $tab_prices['pmvc'];
                }
                    
                $tab_product['price'] = $tab_product_combi['pmvc'] ?? 0;
                $tab_product['wholesale_price'] = $tab_product_combi['price'] ?? 0;

                
                $attr = [];
                $color = str_replace(["'"], [' '], $tab_product_combi['couleur']);
                if (!empty($color)) {
                    $attr['Couleur'] = $color;
                }
                
                $taille = str_replace(["'"], [' '], $tab_product_combi['taille']);
                if (!empty($taille)) {
                    $attr['Taille'] = $taille;
                }

                $tabAssoc = [];
                foreach ($attr as $attributeKey  => $attributeValue) {
                    if (empty($attributeKey) || empty($attributeValue)) {
                        continue;
                    }
                    
                    // Attribute
                    $this->timer->start('getObjectByCrossId_attribut');
                    $idPimAttr = Outils::getObjectByCrossId($attributeKey, 'attribut', $diffusion);
                    $this->timer->stop('getObjectByCrossId_attribut');
                    if (!$idPimAttr) {
                        $tab_attr = [
                            'crossid' => $attributeKey,
                            'id' => $attributeKey,
                            'active' => true,
                            'name' => $attributeKey,
                        ];
                        $attr = json_decode(json_encode($tab_attr));
                        $this->timer->start('putCreateAttribute');
                        $time = microtime(true);
                        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START putCreateAttribute', 2);
                        $idPimAttr = Outils::putCreateAttribute($attr, $diffusion, 1);
                        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END putCreateAttribute : Time = '.(microtime(true) - $time), 2);
                        $this->timer->stop('putCreateAttribute');
                    }
                        
                    // Attribute Value
                    $this->timer->start('getObjectByCrossId_attributValue');
                    $idPimAttrValue = Outils::getObjectByCrossId($attributeValue, 'attributValue', $diffusion);
                    $this->timer->stop('getObjectByCrossId_attributValue');
                    if (!$idPimAttrValue) {
                        $tab_attr_value = [
                            'crossid' => $attributeValue,
                            'id' => $attributeValue,
                            'active' => true,
                            'name' => $attributeValue,
                        ];
                        $attr_value = json_decode(json_encode($tab_attr_value));
                        $this->timer->start('putCreateAttributeValue');
                        $time = microtime(true);
                        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START putCreateAttributeValue', 2);
                        $idPimAttrValue = Outils::putCreateAttributeValue($attr_value, $diffusion, $idPimAttr, '');
                        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END putCreateAttributeValue : Time = '.(microtime(true) - $time), 2);
                        $this->timer->stop('putCreateAttributeValue');
                    }

                    $tabAssoc[] = DataObject::getById($idPimAttrValue);
                }

                // EAN13
                $ean13 = $tab_product_combi['ean13'] ?? '';
                if (empty($ean13) && isset($tab_json[$this->ean13_name])) {
                    $this->timer->start('DbFile_select_ean13');
                    $lineEAN = DbFile::arJsonDecodeRecur(Outils::query('SELECT * FROM `eci_midle_file_ean13_ginkoia` WHERE CODE_ARTICLE = "'.$tab_product['crossid'].'"'), true);
                    $this->timer->stop('DbFile_select_ean13');
                    $tab_product_combi['ean13'] = $lineEAN[0]['CB_FOURN'] ?? '0000000000000';
                }
                $tab_product_combi['ean13'] = ($ean13 && (preg_match('/^[0-9]{0,13}$/', $ean13))) ? $ean13 : '0000000000000';
                $tab_product['ean13'] = '0000000000000';

                // UPC
                $tab_product_combi['upc'] = $tab_product_combi['upc'] ?? '';
                
                // Default on
                $tab_product_combi['default_on'] = $default_on;
                $default_on = false;

                // Déclinaison
                $this->timer->start('getObjectByCrossId_declinaison');
                $idPimDecli = Outils::getObjectByCrossId($tab_product_combi['crossid'], 'declinaison', $diffusion);
                $this->timer->stop('getObjectByCrossId_declinaison');

                if (!$idPimDecli) { // Recherche dans les déclinaisons archivés
                    $idPimDecli = Outils::getObjectByCrossId($tab_product_combi['crossid'], 'declinaison', $diffusion, true);
                    
                    if ($idPimDecli) { // Si nous trouvons une décli archivé alors on la désarchive 
                        Outils::setDesarchivage($idPimDecli);
                    }
                }

                if (!$idPimDecli) {
                    $automatch = false;

                    // AutoMatching : EAN13
                    if ('0000000000000' != $tab_product_combi['ean13']) {
                        $idPimDecli = Outils::getExist($tab_product_combi['ean13'], '', 'ean13', 'declinaison');
                        if ($idPimDecli) {
                            $automatch = $automatchProduct = true;
                        }
                    }

                    // AutoMatching : référence (CODE_ARTICLE)
                    if (!$idPimDecli) {
                        $idPimDecli = Outils::getIDProductDecli('', 'declinaison', '', $tab_product_combi['crossid']);
                        if ($idPimDecli) {
                            $automatch = $automatchProduct = true;
                        }
                    }

                    if ($automatch && $idPimDecli) { // Ajout du crossid
                        $decli = DataObject::getById($idPimDecli);
                        Outils::addCrossid($decli, $diffusion->getId(), $tab_product_combi['crossid']);
                        $prod = DataObject::getById($decli->getParentId());
                        Outils::addCrossid($prod, $diffusion->getId(), $tab_product['crossid']);
                        $prod->forcequeue = true;
                        $prod->save();

                    } else { // Création d'une nouvelle déclinaison
                        $decli = json_decode(json_encode($tab_product_combi));
                        $this->timer->start('putCreateDeclinaison');
                        $time = microtime(true);
                        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START putCreateDeclinaison', 2);
                        $idPimDecli = Outils::putCreateDeclinaison($decli, $diffusion, [], $tabAssoc, []);
                        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END putCreateDeclinaison : Time = '.(microtime(true) - $time), 2);
                        $this->timer->stop('putCreateDeclinaison');
                    }
                }

                $decliList[$tab_product_combi['crossid']] = DataObject::getById($idPimDecli);
            }


            // Images
            $imageList = 0;

            // Marques *
            if (true) {
                $manufacturerCrossid = $tab_product['manufacturer'] ?? 'NC';
                if ($idPim = Outils::getObjectByCrossId($manufacturerCrossid, 'marque', $diffusion)) {
                    $marqueList = DataObject::getById($idPim);
                } else {
                    $tab_marque = [
                        'crossid' => $manufacturerCrossid,
                        'id' => $manufacturerCrossid,
                        'active' => true,
                        'name' => $manufacturerCrossid,
                        'description' => '',
                        'meta_keywords' => '',
                        'meta_title' => '',
                    ];
                    $marque = json_decode(json_encode($tab_marque));
                    $this->timer->start('putCreateMarque');
                    $time = microtime(true);
                    // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START putCreateMarque', 2);
                    $marqueList = DataObject::getById(Outils::putCreateMarque($marque, $diffusion, 1));
                    // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END putCreateMarque : Time = '.(microtime(true) - $time), 2);
                    $this->timer->stop('putCreateMarque');
                }
            }

            // Category * 
            if (true) {
                $lst_multi_categ = [];
                if (isset($tab_json[$this->article_categorie_name])) {
                    $this->timer->start('Dbfile_select_artnomenk');
                    $linesCateg = DbFile::arJsonDecodeRecur(Outils::query('SELECT CODE_NK FROM `eci_midle_file_article_categorie_ginkoia` WHERE CODE_MODEL = "'.$tab_product['id'].'"'), true);
                    $lst_multi_categ = array_column(($linesCateg ?: []), 'CODE_NK');
                    $this->timer->stop('Dbfile_select_artnomenk');
                }
                $lst_unique_multi_categ = array_filter(array_merge([$tab_product['id_category_default'] ?? ''], $lst_multi_categ));

                $i = 0;
                $tab_categ = [];
                $tab_categ[][0] = ['name'=> 'default', 'crossid' => 'default'];
                foreach ($lst_unique_multi_categ as $search_categ) {
                    $categ = [];
                    while (0 != $search_categ && isset($tab_json[$this->categorie_name])) {
                        $this->timer->start('Dbfile_select_nomenk');
                        $item_categ = DbFile::arJsonDecodeRecur(Outils::query('SELECT * FROM `eci_midle_file_categorie_ginkoia` WHERE ID_GINKO = "'.$search_categ.'"'), true);
                        $this->timer->stop('Dbfile_select_nomenk');
                        if (empty($item_categ)) {
                            break;
                        }
                        $categ[] = ['name' => $item_categ[0]['LIB'], 'crossid' => $item_categ[0]['ID_GINKO']];
                        $search_categ = $item_categ[0]['ID_PARENT'];
                    }
                    foreach ($categ as $depth => $cn) {
                        $tab_categ[$i][$depth] = $cn;
                    }
                    $i++;
                }
                $tab_categ = array_map('array_reverse', $tab_categ);
                
                $categList = [];
                foreach ($tab_categ as $branche => $infoCateg) {
                    $parent = '';
                    foreach ($infoCateg as $depth => $dataCateg) {
                        $categ = [
                            'crossid' => $dataCateg['crossid'], //*
                            'id' => $dataCateg['crossid'], //*
                            'name' => $dataCateg['name'], //*
                            // 'active' => '',
                            // 'description' => '',
                            // 'meta_keywords' => '',
                            // 'meta_title' => '',
                            // 'link_rewrite' => '',
                        ];

                        // Vérification du crossid
                        $this->timer->start('getExist_category');
                        $idPim = Outils::getExist($categ['crossid'], $diffusion, 'crossid', 'category');
                        $this->timer->stop('getExist_category');
                        if ($idPim > 0) {
                            if(!$objF = Outils::getCache('object_'.$idPim)) {
                                $objF = DataObject::getById($idPim);   
                                Outils::putCache('object_'.$idPim, $objF);
                            }
                            $categList[] = $objF;
                        } else {
                            $categ = json_decode(json_encode($categ));
                            $this->timer->start('putCreateCategory_'.$tab_product['name']);
                            $this->timer->stop('putCreateCategory_'.$tab_product['name']);
                            $this->timer->start('putCreateCategory');
                            $time = microtime(true);
                            // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START putCreateCategory', 2);
                            $objF = Outils::putCreateCategory($categ, $diffusion, $parent, '');
                            // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END putCreateCategory : Time = '.(microtime(true) - $time), 2);
                            $categList[] = $objF;
                            $idPim = $objF->getId();
                            $this->timer->stop('putCreateCategory');
                        }
                        $parent = $idPim;
                    }
                }
                $tab_product['id_category_default'] = $dataCateg['crossid'];
            }
            
            // Caractéristiques
            if (true) {
                $feat = [];
                foreach ($this->tab_feat as $field => $tag) {
                    if ($tag && array_key_exists($tag, $item)) {
                        if (empty($item[$tag])) {
                            continue;
                        }

                        $feat[$field] = $item[$tag];
                    }
                }

                if ($tab_product['genre']) {
                    if (isset($tab_json[$this->genre_name])) {
                        $this->timer->start('Dbfile_select_genre');
                        $lineGenre = $tab_json[$this->genre_name]->select('GRE_NOM', [['GRE_ID', $tab_product['genre']]]);
                        $lineGenre = DbFile::arJsonDecodeRecur(Outils::query('SELECT GRE_NOM FROM `eci_midle_file_genre_ginkoia` WHERE GRE_ID = "'.$tab_product['genre'].'"'), true);
                        $this->timer->stop('Dbfile_select_genre');
                        $feat['Genre'] = trim($lineGenre[0]['GRE_NOM'] ?? '');
                    }
                }
                
                $caracList = [];
                foreach ($feat as $featureKey  => $featureValue) {
                    if (empty($featureKey) || empty($featureValue)) {
                        continue;
                    }

                    // Carac
                    $caracCrossid = $featureKey;
                    $this->timer->start('getObjectByCrossId_carac');
                    $idPimCarac = Outils::getObjectByCrossId($caracCrossid, 'carac', $diffusion);
                    $this->timer->stop('getObjectByCrossId_carac');
                    if (!$idPimCarac) {
                        $tab_carac = [
                            'crossid' => $caracCrossid,
                            'id' => $caracCrossid,
                            'active' => true,
                            'name' => $caracCrossid,
                        ];
                        $carac = json_decode(json_encode($tab_carac));
                        $this->timer->start('putCreateCarac');
                        $time = microtime(true);
                        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START putCreateCarac', 2);
                        $idPimCarac = Outils::putCreateCarac($carac, $diffusion, 1);
                        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END putCreateCarac : Time = '.(microtime(true) - $time), 2);
                        $this->timer->stop('putCreateCarac');
                    }
                        
                    // Carac Value
                    $caracValueCrossid = $featureValue;
                    $this->timer->start('getObjectByCrossId_caracValue');
                    $idPimCaracValue = Outils::getObjectByCrossId($caracValueCrossid, 'caracValue', $diffusion);
                    $this->timer->stop('getObjectByCrossId_caracValue');
                    if (!$idPimCaracValue) {
                        $tab_carac_value = [
                            'crossid' => $caracValueCrossid,
                            'id' => $caracValueCrossid,
                            'active' => true,
                            'value' => $caracValueCrossid,
                        ];
                        $carac_value = json_decode(json_encode($tab_carac_value));
                        $this->timer->start('putCreateCaracValue');
                        $time = microtime(true);
                        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START putCreateCaracValue', 2);
                        $idPimCaracValue = Outils::putCreateCaracValue($carac_value, $diffusion, $idPimCarac, '');
                        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END putCreateCaracValue : Time = '.(microtime(true) - $time), 2);
                        $this->timer->stop('putCreateCaracValue');
                    }

                    // $caracList[$idPimCarac] = $idPimCaracValue;
                    $caracList[] = DataObject::getById($idPimCaracValue);
                }
            }
            
            // Langue
            $langPS = [];



            // EAN13
            $ean13 = $tab_product['ean13'] ?? '';
            $tab_product['ean13'] = ($ean13 && (preg_match('/^[0-9]{0,13}$/', $ean13))) ? $ean13 : '0000000000000';

            // Manufacturer
            $tab_product['manufacturer'] = $tab_product['manufacturer'] ?? '0';
            
            // Description
            $tab_product['description'] = ($tab_product['description'].' '.$tab_product['composition'].' <br> '.self::arrayToHtmlTable([$feat], true));

            // return $tab_product;

            $prod = json_decode(json_encode($tab_product));

            // Vérification si le produit est déjà connu
            $this->timer->start('getExist_product');
            $idPimProduct = Outils::getExist($tab_product['crossid'], $diffusion, 'crossid', 'product');
            $this->timer->stop('getExist_product');

            
            if (!$idPimProduct) { // Recherche dans les produits archivés
                $idPimProduct = Outils::getObjectByCrossId($tab_product['crossid'], 'product', $diffusion, true);
                
                if ($idPimProduct) { // Si nous trouvons un produit archivé alors on la désarchive 
                    Outils::setDesarchivage($idPimProduct);
                }
            }

            if (!$automatchProduct && !$idPimProduct) {
                $this->timer->start('putCreateProduct');
                $categList = array_unique($categList);
                $time = microtime(true);
                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START putCreateProduct', 2);
                $idPimProduct = Outils::putCreateProduct($prod, $diffusion, $categList, $caracList, $marqueList, $imageList, $decliList, $langPS, 1);
                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END putCreateProduct : Time = '.(microtime(true) - $time), 2);
                $this->timer->stop('putCreateProduct');

                // return $idPimProduct;
            } else {
                if ($idPimProduct) {
                    $this->timer->start('putUpdateDeclinaison');
                    $time = microtime(true);
                    // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START putUpdateDeclinaison_'.json_encode($idPimProduct), 2);
                    Outils::putUpdateDeclinaison($idPimProduct, $decliList, $diffusion);
                    // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END putUpdateDeclinaison_'.json_encode($idPimProduct).' : Time = '.(microtime(true) - $time), 2);
                    $this->timer->stop('putUpdateDeclinaison');

                    
                    $this->timer->start('productIsActive');
                    $this->productIsActive($idPimProduct, $diffusion);
                    $this->timer->stop('productIsActive');
                }
            }

            if ($automatchProduct && $idPimProduct) {
                if ($categList != 0) {
                    $idcat = Outils::getExist($prod->id_category_default, $diffusion->getID(), 'crossid', 'category');
                    $objP = DataObject::getById($idPimProduct);


                    $listDiffusion = $objP->getDiffusion();
                    $listDiffusionActive = $objP->getDiffusions_active();
                    foreach ($categList as $categ) {
                        $diffusionMeta = new ElementMetaData('diffusion', ['id_diffusion', 'is_default'], $categ);
                        $diffusionMeta->setElement($categ);
                        $diffusionMeta->setData(
                            [
                                'id_diffusion' => $diffusion->getID(),
                                'is_default' => ($idcat == $categ->getId() ? 1 : ''),
                            ],
                        );

                        $listDiffusion[] = $diffusionMeta;
                    }
                    $listDiffusionActive[] = $diffusion->getID();

                    // replace old ones by new set and save
                    $objP->setDiffusion($listDiffusion);
                    $objP->setDiffusions_active(array_unique($listDiffusionActive));
                    $objP->setCategory_default($idcat);
                    \Pimcore\Model\Version::disable();

                    try {
                        $objP->save(['versionNote' => '(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ')']);
                    } catch (\Exception $e) {
                        Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur integration : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile(), 3, $objP, 'error_save');
                        Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur integration : ' . json_encode($prod), 3, $objP, 'error_save');
                        // return $e->getMessage();
                    }
                    \Pimcore\Model\Version::enable();
                }
            }

            if ('75537' == $idPimProduct) {
                // return 'bloqué';
            }
        }

        return ($json->currentLine >= $json->maxLine) ? true : $json->currentLine;
    }
    
    public function cronUpdateFluctuMinus(array $params)
    {
        $cron = $params['nbParent'] ?? 'manualTest';
        $nbCron = $params['nbCron'] ?? 1;
        $stopTime = $params['stopTime'] ?? (time() + 15);
        $connector = new connector();
        $diffusion = $connector->getDiffusion();

        $lstGinkoia = array_column(Outils::query('SELECT DISTINCT(CODE_MODELE) FROM `eci_midle_file_catalogue_ginkoia`'), 'CODE_MODELE');
        
        $lists = ecMiddleController::getRequireOrDependBy($diffusion->getId(), 'object', false);
        
        foreach ($lists as $i => $item) {
            if ($i < $nbCron) {
                continue;
            }
            if (time() > $stopTime) {
                return $i;
            }
            $obj = DataObject::getById($item['id']);
            if (is_object($obj) || ('product' != $obj->getClassName())) {
                continue; // Ce n'est pas un produit
            }

            $crossid = '';
            $lstCrossid = $obj->getCrossid();
            foreach ($lstCrossid as $objCrossid) {
                if ($objCrossid->getElementId() != $diffusion->getId()) {
                    continue; // Nous ne sommes pas responsable de ces diffusions
                }
                $crossid = $objCrossid->getData()['ext_id'] ?? '';
            }

            if (empty($crossid)) {
                continue; // Le crossid est vide
            }
            if (in_array($crossid, $lstGinkoia)) {
                continue; // Le produit est toujours dans le flux
            }

            if (!Outils::hasTag($obj, 'deref')) {
                Outils::addTag($obj, 'deref');
            }

            // Mettre les stock des déclinaisons à 0
            try {
                $this->timer->start('setNullStock');
                $time = microtime(true);
                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START setNullStock '.$obj->getId(), 2);
                Outils::resetStock($obj->getId());
                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END setNullStock '.$obj->getId().' : Time = '.(microtime(true) - $time), 2);
                $this->timer->stop('setNullStock');

            } catch (Exception $e) {
                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur MAJ setNullStock ('.$obj->getId().') : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile(), 3, '', 'error_save');
            }

            if (1 == $obj->getPublished()) {
                // Modification du produit
                try {
                    $obj->setPublished(false); // On désactive le produit
                    $obj->save(['versionNote' => '(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ')']);
                } catch (Exception $e) {
                    Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur Deref : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile(), 3, $obj, 'error_save');
                }
            }
        }

        return true;
    }

    public function cronUpdateFluctuMinusFast(array $params)
    {
        $cron = $params['nbParent'] ?? 'manualTest';
        $nbCron = $params['nbCron'] ?? 0;
        $stopTime = $params['stopTime'] ?? (time() + 15);
        $connector = new connector();
        $diffusion = $connector->getDiffusion();

        $fileName = $this->catalog_name.'_tmp';
        $this->collect->addInfo('START '.__FUNCTION__.', nbCron ('.$nbCron.')');
        
        if (!$nbCron) { // Premier tour de boucle
            $this->timer->start('DbFile');
            $tab_json = [];
            $json = new DbFile($this->catalog_name);

            foreach ($tab_json as $objBigjson) {
                $objBigjson->deleteIndex();
            }
            $json->deleteIndex();

            $json->buildIndex('CODE_MODELE');
            $this->timer->stop('DbFile');

            // Récupérer la liste des produits qui ne sont plus au catalogue
            $sql = 'SELECT p.oo_id AS idPimProduct, mp.data as crossid, g.CODE_MODELE
                FROM object_query_' . Outils::getIDClass('product') . ' p
                LEFT JOIN object_metadata_' . Outils::getIDClass('product') . ' mp ON p.oo_id = mp.id AND mp.dest_id = '.$diffusion->getId().' AND mp.`column` = "ext_id"
                LEFT JOIN ' . $json->getTableName() . ' g ON g.CODE_MODELE = mp.data
                WHERE 1
                AND (p.archive IS NULL OR p.archive = 0)
                AND mp.id IS NOT NULL
                AND g.CODE_MODELE IS NULL
                GROUP BY p.oo_id
                ORDER BY p.oo_id';
            $this->collect->addInfo('Query : '.$sql);
            
            $this->timer->start('Query');
            $lst = Outils::query($sql);
            $this->collect->addInfo('MaxLine ('.count($lst).')');
            $this->timer->stop('Query');

            if (is_array($lst) && empty($lst)) {
                return true;
            }

            // Transformation
            $this->timer->start('buildFromArray');
            $ret = DbFile::buildFromArray($lst, $fileName);
            $this->timer->stop('buildFromArray');
            if (!is_numeric($ret)) {
                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur lors de la création du DbFile '.$fileName.' : '.var_export($ret, true), 3);
                return false;
            }
        }

        $json = new DbFile($fileName);

        $json->go($nbCron);
        while ($item = $json->read()) {
            if ((time() > $stopTime) && ($json->currentLine > $nbCron)) {
                return $json->currentLine;
            }
            
            $obj = DataObject::getById($item['idPimProduct']);
            if (!is_object($obj) || ('product' != $obj->getClassName())) {
                continue; // Ce n'est pas un produit
            }

            if (!Outils::hasTag($obj, 'deref')) {
                Outils::addTag($obj, 'deref');
            }

            // Mettre les stock des déclinaisons à 0
            try {
                $this->timer->start('setNullStock');
                $time = microtime(true);
                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START setNullStock '.$obj->getId(), 2);
                Outils::resetStock($obj->getId());
                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END setNullStock '.$obj->getId().' : Time = '.(microtime(true) - $time), 2);
                $this->timer->stop('setNullStock');

            } catch (Exception $e) {
                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur MAJ setNullStock ('.$obj->getId().') : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile(), 3, '', 'error_save');
            }

            if (1 == $obj->getPublished()) {
                // Modification du produit
                try {
                    $obj->setPublished(false); // On désactive le produit
                    $obj->save(['versionNote' => '(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ')']);
                } catch (Exception $e) {
                    Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur Deref : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile(), 3, $obj, 'error_save');
                }
            }
        }

        return true;
    }
    
    public function calculatePrices($pa, $pv, $tr = 20)
    {
        $price = (float) str_replace(',', '.', $pa);
        $pmvc = (float) str_replace(',', '.', $pv);
        $taxrate = (float) str_replace(',', '.', $tr);

        // correction of price
        // $price = $pattc * (100 / (100 + $taxrate));
        // $pmvc = $pvttc * (100 / (100 + $taxrate));

        return ['price' => round($price, 6), 'pmvc' => round($pmvc, 6)];
    }

    /**
     * MAJ Stock
     */
    public function cronUpdateStock(array $params)
    {
        $cron = $params['nbParent'] ?? 'manualTest';
        $nbCron = $params['nbCron'] ?? 0;
        // $nbCron = 761;
        $stopTime = $params['stopTime'] ?? (time() + 15);
        $connector = new connector();
        $diffusion = $connector->getDiffusion();

        $json = new DbFile($this->stock_name);

        $tab_json = [];
        
        // reset des indexes des json, sinon on risque de travailler avec des indexes obsolètes
        if (!$nbCron) {
            foreach ($tab_json as $objBigjson) {
                $objBigjson->deleteIndex();
            }
            $json->deleteIndex();
        }

        $json->go($nbCron);
        while ($item = $json->read()) {
            if ((time() > $stopTime) && ($json->currentLine > $nbCron)) {
                return $json->currentLine;
            }

            $tab_product = [];
            foreach ($this->tab_stock as $field => $tag) {
                if ($tag && array_key_exists($tag, $item)) {
                    $tab_product[$field] = trim($item[$tag]);
                }
            }
            if ('2001002779831' != $item['CODE_ARTICLE']) {
                // continue;
            }

            // Récupération des IDs de produit / déclinaisons
            $this->timer->start('getObjectByCrossId_declinaison');
            $idPimDecli = Outils::getObjectByCrossId($tab_product['crossid'], 'declinaison', $diffusion);
            $this->timer->stop('getObjectByCrossId_declinaison');
            if (!$idPimDecli) { // Produit Simple
                continue;
            } else { // Produit décliné
                $this->timer->start('getById_produit');
                $idPimProduct = DataObject::getById($idPimDecli)->getParentId();
                $this->timer->stop('getById_produit');
            }

            $objP = DataObject::getById($idPimProduct);
            if (!($objP instanceof Product)) {
                continue;
            }


            if (71924 != $idPimProduct) {
                // continue;
            }
            $this->collect->addInfo($idPimProduct.'-'.$idPimDecli);

            // Récupération de l'ID d'entrepôt
            $this->timer->start('getObjectByCrossId_entrepot');
            $idPimEntrepot =  Outils::getObjectByCrossId($tab_product['location'], 'entrepot', $diffusion);
            $this->timer->stop('getObjectByCrossId_entrepot');
            if (!$idPimEntrepot) {
                $tab_entrepot = [
                    'id' => $tab_product['location'],
                    'active' => true,
                    'name' => $tab_product['location'],
                    // 'physique' => 1,
                ];
                $entrepot = json_decode(json_encode($tab_entrepot));
                $this->timer->start('putCreateDepot');
                $idPimEntrepot = Outils::putCreateDepot($entrepot, $diffusion, 1, '');
                $this->timer->stop('putCreateDepot');
            }

            // Conversion date
            $date_U = Carbon::parse($tab_product['lastUpdate'] ?? date('Y-m-d H:i:s'))->format('U');            

            /**
             * MAJ des stocks
             */
            $stock = $tab_product['stock'] ?? 0;
            $prix = $tab_product['pmvc'] ?? 0;

            // $stocks_en_cours = Outils::getStockProduct($idPimProduct, $idPimDecli, 'physique', $idPimEntrepot, $diffusion, 0, 0);
            // if ($stocks_en_cours == $stock) {
            //     Outils::update($json->getTableName(), ['log' => 'OK stock identique, pas de mouvement à créer'], ['i' => (int)$json->currentLine]);
            //     continue;
            // }

            try {
                $this->timer->start('addMouvementStock');
                $time = microtime(true);
                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START addMouvementStock '.json_encode($idPimProduct).'-'.json_encode($idPimDecli), 2);
                $id_mvt = Outils::addMouvementStock(
                    $idPimProduct,
                    $idPimDecli,
                    '=', // Delta => +/-/=
                    0, // Type de stock => 0 pour Physique // 1 pour Réserve // 2 pour Attente
                    0,
                    $stock,
                    $idPimEntrepot, // Entrepot
                    $diffusion, // Diffusion
                    0, // Source
                    '', // Emplacement
                    'MAJ from '.$connector->diffusion_name.' - Produit '.$idPimProduct.'-'.$idPimDecli.' - Stock '.$stock.' - Location '.$tab_product['location'].' - Crossid '.$tab_product['crossid'],
                    $prix, // Prix
                    $date_U
                );
                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END addMouvementStock ('.$id_mvt.') '.$idPimProduct.'-'.$idPimDecli.' : Time = '.(microtime(true) - $time), 2);
                $this->timer->stop('addMouvementStock');
                // $tab_log[] = [
                //     $idPimProduct,
                //     $idPimDecli,
                //     $stock
                // ];

                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - MAJ Stock ('.$idPimProduct.'-'.$idPimDecli.') : mouvement -> ' . $id_mvt . ', stock '.$tab_product['stock'].', location -> '.$tab_product['location'], 2);
            } catch (Exception $e) {
                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur MAJ Stock ('.$idPimProduct.'-'.$idPimDecli.') : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile(), 3, '', 'error_save');
            }
        }

        return true;
    } 

    public function cronUpdateEntrepot(array $params)
    {
        $cron = $params['nbParent'] ?? 'manualTest';
        $nbCron = $params['nbCron'] ?? 0;
        $stopTime = $params['stopTime'] ?? (time() + 15);
        $connector = new connector();
        $diffusion = $connector->getDiffusion();
        
        $fileName = $this->stock_name.'_entrepot_tmp';
        $this->collect->addInfo('START '.__FUNCTION__.', nbCron ('.$nbCron.')');

        $this->timer->start('DbFile');
        $tab_json = [];
        $json = new DbFile($this->stock_name);

        if (!$nbCron) { // Premier tour de boucle
            foreach ($tab_json as $objBigjson) {
                $objBigjson->deleteIndex();
            }
            $json->deleteIndex();

            $json->buildIndex('MAG_ID');
            $this->timer->stop('DbFile');
            
            $sql = 'SELECT DISTINCT(g.MAG_ID) AS location FROM ' . $json->getTableName() . ' g';
            $this->collect->addInfo('Query : '.$sql);

            $this->timer->start('Query');
            $lst = Outils::query($sql);
            $this->collect->addInfo('MaxLine ('.count($lst).')');
            $this->timer->stop('Query');

            if (is_array($lst) && empty($lst)) {
                return false;
            }

            // Transformation
            $this->timer->start('buildFromArray');
            $ret = DbFile::buildFromArray($lst, $fileName);
            $this->timer->stop('buildFromArray');
            if (!is_numeric($ret)) {
                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur lors de la création du DbFile '.$fileName.' : '.var_export($ret, true), 3);
                return false;
            }
        }

        $json = new DbFile($fileName);

        $json->go($nbCron);
        while ($item = $json->read()) {
            if ((time() > $stopTime) && ($json->currentLine > $nbCron)) {
                return $json->currentLine;
            }

            $this->collect->addInfo('Entrepot ('.$item['location'].')');

            try {
                // Récupération de l'ID d'entrepôt
                $this->timer->start('getObjectByCrossId_entrepot');
                $idPimEntrepot =  Outils::getObjectByCrossId($item['location'], 'entrepot', $diffusion);
                $this->timer->stop('getObjectByCrossId_entrepot');
                if (!$idPimEntrepot) {
                    $tab_entrepot = [
                        'id' => $item['location'],
                        'active' => true,
                        'name' => $item['location'],
                        // 'physique' => 1,
                    ];
                    $entrepot = json_decode(json_encode($tab_entrepot));
                    $this->timer->start('putCreateDepot');
                    $idPimEntrepot = Outils::putCreateDepot($entrepot, $diffusion, 1, '');
                    $this->timer->stop('putCreateDepot');
                }
            } catch (Exception $e) {
                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur MAJ Entrepot ('.$item['location'].') : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile());
            }
        }

        return true;
    }

    public function cronUpdateStockFast(array $params)
    {
        $cron = $params['nbParent'] ?? 'manualTest';
        $nbCron = $params['nbCron'] ?? 0;
        $stopTime = $params['stopTime'] ?? (time() + 15);
        $connector = new connector();
        $diffusion = $connector->getDiffusion();
        $date_U = Carbon::parse(date('Y-m-d H:i:s'))->format('U');
        
        $fileName = $this->stock_name.'_tmp';
        $this->collect->addInfo('START '.__FUNCTION__.', nbCron ('.$nbCron.')');

        if (!$nbCron) { // Premier tour de boucle
            $this->timer->start('DbFile');
            $tab_json = [];
            $json = new DbFile($this->stock_name);

            foreach ($tab_json as $objBigjson) {
                $objBigjson->deleteIndex();
            }
            $json->deleteIndex();

            $json->buildIndex('CODE_ARTICLE');
            $json->buildIndex('MAG_ID');
            $json->buildIndex('QTE_STOCK');
            $this->timer->stop('DbFile');
            
            // Récupérer la liste des lignes dont les stocks ont été modifié ou sont vide
            $sql = 'SELECT g.CODE_ARTICLE AS crossid, g.MAG_ID AS location, g.QTE_STOCK AS newStock, p.id AS idPimProduct, d.oo_id AS idPimDecli, e.id AS idPimEntrepot, s.quantity_physique, s.stock_entrepot__id
                FROM ' . $json->getTableName() . ' g
                LEFT JOIN object_' . Outils::getIDClass('entrepot') . ' e ON e.crossid LIKE "%,object|'.$diffusion->getId().',%" AND g.MAG_ID = e.name AND (e.archive IS NULL OR e.archive = 0)
                LEFT JOIN object_' . Outils::getIDClass('declinaison') . ' d ON d.crossid LIKE "%,object|'.$diffusion->getId().',%" AND g.CODE_ARTICLE = d.reference_declinaison AND (d.archive IS NULL OR d.archive = 0)
                LEFT JOIN object_' . Outils::getIDClass('product') . ' p ON p.crossid LIKE "%,object|'.$diffusion->getId().',%" AND d.parentId = p.id AND (p.archive IS NULL OR p.archive = 0)
                LEFT JOIN object_' . Outils::getIDClass('stock') . ' s ON p.id = s.stock_product__id AND d.id = s.stock_declinaison__id AND (s.archive IS NULL OR s.archive = 0) AND e.id = s.stock_entrepot__id
                WHERE 1
                AND d.id IS NOT NULL
                AND p.id IS NOT NULL
                AND e.id IS NOT NULL
                AND ((g.QTE_STOCK != s.quantity_physique) OR (g.QTE_STOCK != 0 AND s.id IS NOT NULL))
                ORDER BY p.id ASC';
            $this->collect->addInfo('Query : '.$sql);

            $this->timer->start('Query');
            $lst = Outils::query($sql);
            $this->collect->addInfo('MaxLine ('.count($lst).')');
            $this->timer->stop('Query');

            if (is_array($lst) && empty($lst)) {
                return true;
            }

            // Transformation
            $this->timer->start('buildFromArray');
            $ret = DbFile::buildFromArray($lst, $fileName);
            $this->timer->stop('buildFromArray');
            if (!is_numeric($ret)) {
                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur lors de la création du DbFile '.$fileName.' : '.var_export($ret, true), 3);
                return false;
            }
        }

        $json = new DbFile($fileName);

        $json->go($nbCron);
        while ($item = $json->read()) {
            if ((time() > $stopTime) && ($json->currentLine > $nbCron)) {
                return $json->currentLine;
            }

            $this->collect->addInfo('Stock ('.$item['idPimProduct'].'-'.$item['idPimDecli'].') => '.$item['crossid']. ' // newStock '.$item['newStock']. ' // Entrepot '.$item['location']);

            try {
                $this->timer->start('addMouvementStock');
                $time = microtime(true);
                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START addMouvementStock '.json_encode($idPimProduct).'-'.json_encode($idPimDecli), 2);
                $id_mvt = Outils::addMouvementStock(
                    $item['idPimProduct'],
                    $item['idPimDecli'],
                    '=', // Delta => +/-/=
                    0, // Type de stock => 0 pour Physique // 1 pour Réserve // 2 pour Attente
                    0,
                    $item['newStock'],
                    $item['idPimEntrepot'], // Entrepot
                    $diffusion, // Diffusion
                    0, // Source
                    '', // Emplacement
                    'MAJ from '.$connector->diffusion_name.' - Produit '.$item['idPimProduct'].'-'.$item['idPimDecli'].' - Stock '.$item['newStock'].' - Location '.$item['location'].' - Crossid '.$item['crossid'],
                    '', // Prix
                    $date_U
                );
                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END addMouvementStock ('.$id_mvt.') '.$idPimProduct.'-'.$idPimDecli.' : Time = '.(microtime(true) - $time), 2);
                $this->timer->stop('addMouvementStock');
            } catch (Exception $e) {
                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur MAJ Stock ('.$item['idPimProduct'].'-'.$item['idPimDecli'].') : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile());
            }
        }

        return true;
    }
    
    /**
     * MAJ PRIX
     */
    public function cronUpdatePrice(array $params)
    {
        $cron = $params['nbParent'] ?? 'manualTest';
        $nbCron = $params['nbCron'] ?? 0;
        // $nbCron = 13;
        $stopTime = $params['stopTime'] ?? (time() + 15);
        $connector = new connector();
        $diffusion = $connector->getDiffusion();

        $json = new DbFile($this->catalog_price_name);

        $tab_json = [];
        $tab_json[$this->prix_price_name] = new DbFile($this->prix_price_name);
        if ($this->config['use_oc']) {
            $tab_json[$this->oc_name] = new DbFile($this->oc_name);
        }
        
        // reset des indexes des json, sinon on risque de travailler avec des indexes obsolètes
        if (!$nbCron) {
            foreach ($tab_json as $objBigjson) {
                $objBigjson->deleteIndex();
            }
            $json->deleteIndex();

            $tab_json[$this->prix_price_name]->buildIndex('CODE_ARTICLE');
            if ($this->config['use_oc']) {
                $tab_json[$this->oc_name]->buildIndex('CODE_ARTICLE');
            }
        }

        $json->go($nbCron);
        while ($item = $json->read()) {
            if ((time() > $stopTime) && ($json->currentLine > $nbCron)) {
                return $json->currentLine;
            }

            $tab_product = [];
            foreach ($this->tab_cat as $field => $tag) {
                if ($tag && array_key_exists($tag, $item)) {
                    $tab_product[$field] = trim($item[$tag]);
                }
            }
            if ('2001002779831' != $item['CODE_ARTICLE']) {
                // continue;
            }

            // Récupération des IDs de produit / déclinaisons
            $this->timer->start('getObjectByCrossId_declinaison');
            $idPimDecli = Outils::getObjectByCrossId($tab_product['decl_reference'], 'declinaison', $diffusion);
            $this->timer->stop('getObjectByCrossId_declinaison');
            if (!$idPimDecli) { // Produit Simple
                continue;
            } else { // Produit décliné
                $this->timer->start('getById_produit');
                $idPimProduct = DataObject::getById($idPimDecli)->getParentId();
                $this->timer->stop('getById_produit');
            }

            if (74982 != $idPimProduct) {
                // continue;
            }
            // $this->collect->addInfo($idPimProduct.'-'.$idPimDecli.' => '.$tab_product['decl_reference']);

            
            $rate = is_numeric($tab_product['id_tax'] ?? '') ? $tab_product['id_tax'] : $this->tva;

            /**
             * MAJ des prix
             */
            $this->timer->start('DbFile_select_prix');
	        $linePrice = DbFile::arJsonDecodeRecur(Outils::query('SELECT * FROM `eci_midle_file_prix_price_ginkoia` WHERE CODE_ARTICLE = "'.$tab_product['decl_reference'].'"'), true);
            $this->timer->stop('DbFile_select_prix');
            if (isset($linePrice[0][$this->tab_attr['price']])) {
                $tab_product['price'] = str_replace(',', '.', $linePrice[0][$this->tab_attr['price']]);
                $tab_product['pmvc'] = str_replace(',', '.', $linePrice[0][$this->tab_attr['pmvc']]);
                // $tab_prices = $this->calculatePrices($tab_product['price'], $tab_product['pmvc'], $rate);
                // $tab_product['price'] = $tab_prices['price'];
                // $tab_product['pmvc'] = $tab_prices['pmvc'];

                try {
                    $this->timer->start('putCreatePriceSell');
                    $time = microtime(true);
                    // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START putCreatePriceSell '.$idPimProduct.'-'.$idPimDecli, 2);
                    Outils::putCreatePriceSell($idPimProduct, $idPimDecli, $diffusion->getId(), 1, 1, 1, (float) $tab_product['pmvc']);
                    // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END putCreatePriceSell '.$idPimProduct.'-'.$idPimDecli.' : Time = '.(microtime(true) - $time), 2);
                    $this->timer->stop('putCreatePriceSell');

                    // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - MAJ PRIX ('.$idPimProduct.'-'.$idPimDecli.') : prix -> ' . $tab_product['pmvc'] . ', stock '.$tab_product['stock'].', location -> '.$tab_product['location'], 2);
                } catch (Exception $e) {
                    Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur MAJ PRIX ('.$idPimProduct.'-'.$idPimDecli.') : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile(), 3, '', 'error_save');
                }

                /**
                 * MAJ des OC
                 */
                if (isset($tab_json[$this->oc_name])) {
                    $this->timer->start('DbFile_select_OC');
                    $linesOC = DbFile::arJsonDecodeRecur(Outils::query('SELECT * FROM `eci_midle_file_oc_ginkoia` WHERE CODE_ARTICLE = "'.$tab_product['decl_reference'].'"'), true);
                    $this->timer->stop('DbFile_select_OC');
                    foreach ($linesOC as $lineOC) {
                        if ('01/01/1950' == $lineOC['DATE_DEBUT']) {
                            $lineOC['DATE_DEBUT'] = '01/01/2020';
                        }
                        $begin = Carbon::parse(implode('-', array_reverse(explode('/', $lineOC['DATE_DEBUT']))).' 00:00:00')->format('Y-m-d\\TH:i');
                        $end = Carbon::parse(implode('-', array_reverse(explode('/', $lineOC['DATE_FIN']))).' 23:59:59')->format('Y-m-d\\TH:i');

                        $this->collect->addInfo('OC '.$tab_product['decl_reference'].' : '.json_encode([$begin,$end]));
                        try {
                            $this->timer->start('putCreatePriceSell_OC');
                            $time = microtime(true);
                            // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START putCreatePriceSell for OC '.$idPimProduct.'-'.$idPimDecli, 2);
                            Outils::putCreatePriceSell(id_prod: $idPimProduct, id_declinaison: $idPimDecli, id_diffusion: $diffusion->getId(), price: (float) str_replace(',', '.', $lineOC['PRIX_ARTICLE']), date_start: $begin, date_end: $end);
                            // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END putCreatePriceSell for OC '.$idPimProduct.'-'.$idPimDecli.' : Time = '.(microtime(true) - $time), 2);
                            $this->timer->start('putCreatePriceSell_OC');
                    
                            // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - MAJ OC ('.$idPimProduct.'-'.$idPimDecli.') : prix -> ' . $tab_product['pmvc'] . ', stock '.$tab_product['stock'].', location -> '.$tab_product['location'], 2);
                        } catch (Exception $e) {
                            Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur MAJ OC ('.$idPimProduct.'-'.$idPimDecli.') : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile(), 3, '', 'error_save');
                        }
                    }
                }
            }
        }

        return true;
    }

    public function cronUpdatePriceFast(array $params)
    {
        $cron = $params['nbParent'] ?? 'manualTest';
        $nbCron = $params['nbCron'] ?? 0;
        $stopTime = $params['stopTime'] ?? (time() + 15);
        $connector = new connector();
        $diffusion = $connector->getDiffusion();

        $fileName = $this->prix_price_name.'_tmp';
        $this->collect->addInfo('START '.__FUNCTION__.', nbCron ('.$nbCron.')');
        
        // reset des indexes des json, sinon on risque de travailler avec des indexes obsolètes
        if (!$nbCron) {
            $this->timer->start('DbFile');
            $tab_json = [];
            $json = new DbFile($this->prix_price_name);

            foreach ($tab_json as $objBigjson) {
                $objBigjson->deleteIndex();
            }
            $json->deleteIndex();

            $json->buildIndex('CODE_ARTICLE');
            $json->buildIndex('PXVTE');
            $this->timer->stop('DbFile');

            // Récupérer la liste des lignes dont les déclinaisons n'ont pas le bon prix ou n'ont pas de prix
            $sql = 'SELECT g.CODE_ARTICLE AS crossid, REPLACE(g.PXVTE, ",", ".") AS newPrice, ps.id AS idPimPriceSelling, ps.price_ttc AS oldPrice
                FROM ' . $json->getTableName() . ' g
                LEFT JOIN object_' . Outils::getIDClass('declinaison') . ' d ON d.reference_declinaison = g.CODE_ARTICLE AND d.crossid LIKE "%,object|'.$diffusion->getId().',%" AND (d.archive IS NULL OR d.archive = 0)
                LEFT JOIN object_' . Outils::getIDClass('product') . ' p ON d.parentId = p.id AND d.crossid LIKE "%,object|'.$diffusion->getId().',%" AND (p.archive IS NULL OR p.archive = 0)
                LEFT JOIN object_' . Outils::getIDClass('priceSelling') . ' ps ON d.id = ps.decli__id AND ps.date_start IS NULL AND ps.date_end IS NULL AND (ps.archive IS NULL OR ps.archive = 0)
                WHERE 1
                AND d.id IS NOT NULL
                AND p.id IS NOT NULL
                AND ((REPLACE(g.PXVTE, ",", ".") != ps.price_ttc) OR ps.price_ttc IS NULL)';
            $this->collect->addInfo('Query : '.$sql);
            
            $this->timer->start('Query');
            $lst = Outils::query($sql);
            $this->collect->addInfo('MaxLine ('.count($lst).')');
            $this->timer->stop('Query');

            if (is_array($lst) && empty($lst)) {
                return true;
            }

            // Transformation
            $this->timer->start('buildFromArray');
            $ret = DbFile::buildFromArray($lst, $fileName);
            $this->timer->stop('buildFromArray');
            if (!is_numeric($ret)) {
                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur lors de la création du DbFile '.$fileName.' : '.var_export($ret, true), 3);
                return false;
            }
        }

        $json = new DbFile($fileName);

        $json->go($nbCron);
        while ($item = $json->read()) {
            if ((time() > $stopTime) && ($json->currentLine > $nbCron)) {
                return $json->currentLine;
            }

            $this->timer->start('getObjectByCrossId_declinaison');
            $idPimDecli = Outils::getObjectByCrossId($item['crossid'], 'declinaison', $diffusion);
            $this->timer->stop('getObjectByCrossId_declinaison');
            if (!$idPimDecli) { // Produit Simple
                continue;
            } else { // Produit décliné
                $this->timer->start('getById');
                $idPimProduct = DataObject::getById($idPimDecli)->getParentId();
                $this->timer->stop('getById');
            }

            $this->collect->addInfo('Price ('.$idPimProduct.'-'.$idPimDecli.') => '.$item['crossid']. ' // newPrice '.$item['newPrice']. ' // oldPrice ('.(int) $item['idPimPriceSelling'].') '.$item['oldPrice']);
            
            try {
                $time = microtime(true);
                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START putCreatePriceSell '.$idPimProduct.'-'.$idPimDecli, 2);
                $this->timer->start('putCreatePriceSell');
                Outils::putCreatePriceSell($idPimProduct, $idPimDecli, $diffusion->getId(), 1, 1, 1, (float) $item['newPrice']);
                $this->timer->stop('putCreatePriceSell');
                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END putCreatePriceSell '.$idPimProduct.'-'.$idPimDecli.' : Time = '.(microtime(true) - $time), 2);
            } catch (Exception $e) {
                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur MAJ PRIX ('.$idPimProduct.'-'.$idPimDecli.') : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile());
            }
        }

        return true;
    }

    public function cronUpdatePromo(array $params)
    {
        $cron = $params['nbParent'] ?? 'manualTest';
        $nbCron = $params['nbCron'] ?? 0;
        $stopTime = $params['stopTime'] ?? (time() + 15);
        $connector = new connector();
        $diffusion = $connector->getDiffusion();
        $now = Carbon::parse(time())->format('Y-m-d\\TH:i');

        if (!$this->config['use_oc']) {
            return true;
        }
        
        $this->timer->start('DbFile');
        $tab_json = [];
        $json = new DbFile($this->oc_name);
        
        // reset des indexes des json, sinon on risque de travailler avec des indexes obsolètes
        if (!$nbCron) {
            foreach ($tab_json as $objBigjson) {
                $objBigjson->deleteIndex();
            }
            $json->deleteIndex();
        }
        $this->timer->stop('DbFile');

        
        $json->go($nbCron);
        while ($item = $json->read()) {
            if ((time() > $stopTime) && ($json->currentLine > $nbCron)) {
                return $json->currentLine;
            }

            $this->timer->start('getObjectByCrossId_declinaison');
            $idPimDecli = Outils::getObjectByCrossId($item['CODE_ARTICLE'], 'declinaison', $diffusion);
            $this->timer->stop('getObjectByCrossId_declinaison');
            if (!$idPimDecli) { // Produit Simple
                continue;
            } else { // Produit décliné
                $this->timer->start('getById');
                $idPimProduct = DataObject::getById($idPimDecli)->getParentId();
                $this->timer->stop('getById');
            }

            if ('01/01/1950' == $item['DATE_DEBUT']) {
                $lineOC['DATE_DEBUT'] = '01/01/2020';
            }
            $begin = Carbon::parse(implode('-', array_reverse(explode('/', $item['DATE_DEBUT']))).' 00:00:00')->format('Y-m-d\\TH:i');
            $end = Carbon::parse(implode('-', array_reverse(explode('/', $item['DATE_FIN']))).' 23:59:59')->format('Y-m-d\\TH:i');

            if ($now > $begin) {
                continue;
            }
            if ($end < $now) {
                continue;
            }

            $this->collect->addInfo('OC '.$item['CODE_ARTICLE'].' : '.json_encode([$begin,$end]));
            try {
                $time = microtime(true);
                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START putCreatePriceSell for OC '.$idPimProduct.'-'.$idPimDecli, 2);
                $this->timer->start('putCreatePriceSell');
                Outils::putCreatePriceSell(id_prod: $idPimProduct, id_declinaison: $idPimDecli, id_diffusion: $diffusion->getId(), price: (float) str_replace(',', '.', $lineOC['PRIX_ARTICLE']), date_start: $begin, date_end: $end);
                $this->timer->start('putCreatePriceSell');
                // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END putCreatePriceSell for OC '.$idPimProduct.'-'.$idPimDecli.' : Time = '.(microtime(true) - $time), 2);
            } catch (Exception $e) {
                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur MAJ OC ('.$idPimProduct.'-'.$idPimDecli.') : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile(), 3, '', 'error_save');
            }
        }

        return true;
    }

    public function cronCleanPrice(array $params)
    {
        $cron = $params['nbParent'] ?? 'manualTest';
        $nbCron = $params['nbCron'] ?? 0;
        $stopTime = $params['stopTime'] ?? (time() + 15);
        $connector = new connector();
        $diffusion = $connector->getDiffusion();

        if (!$this->config['use_oc']) {
            return true;
        }
        
        $fileName = $this->oc_name.'_tmp';
        $this->collect->addInfo('START '.__FUNCTION__.', nbCron ('.$nbCron.')');
        
        // reset des indexes des json, sinon on risque de travailler avec des indexes obsolètes
        if (!$nbCron) { // Premier tour de boucle
            $this->timer->start('DbFile');
            $tab_json = [];
            $json = new DbFile($this->oc_name);

            foreach ($tab_json as $objBigjson) {
                $objBigjson->deleteIndex();
            }
            $json->deleteIndex();

            $json->buildIndex('CODE_ARTICLE');
            $json->buildIndex('PRIX_ARTICLE');
            $this->timer->stop('DbFile');

            // Archive les PriceSelling qui sont trop ancienne et celle qui ne sont plus dans le flux
            $sql = 'SELECT ps.id AS idPriceSell, d.crossid, ps.price_ttc, REPLACE(oc.PRIX_ARTICLE, ",", ".") AS prix_promo
                FROM object_' . Outils::getIDClass('priceSelling') . ' ps
                LEFT JOIN object_' . Outils::getIDClass('declinaison') . ' d ON d.parentId = ps.decli__id AND d.crossid LIKE "%,object|'.$diffusion->getId().',%" AND (d.archive IS NULL OR d.archive = 0)
                LEFT JOIN ' . $json->getTableName() . ' oc ON oc.CODE_ARTICLE = d.reference_declinaison
                WHERE 1
                AND d.id IS NOT NULL
                AND ps.date_start IS NOT NULL
                AND ps.date_end IS NOT NULL
                AND (ps.archive IS NULL OR ps.archive = 0)
                AND (oc.CODE_ARTICLE IS NULL OR UNIX_TIMESTAMP(NOW()) > ps.date_end)
                ORDER BY ps.id';
            $this->collect->addInfo('Query : '.$sql);

            $this->timer->start('Query');
            $lst = Outils::query($sql);
            $this->collect->addInfo('MaxLine ('.count($lst).')');
            $this->timer->stop('Query');

            if (is_array($lst) && empty($lst)) {
                return true;
            }

            // Transformation
            $this->timer->start('buildFromArray');
            $ret = DbFile::buildFromArray($lst, $fileName);
            $this->timer->stop('buildFromArray');
            if (!is_numeric($ret)) {
                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur lors de la création du DbFile '.$fileName.' : '.var_export($ret, true), 3);
                return false;
            }
        }

        $json = new DbFile($fileName);

        $json->go($nbCron);
        while ($item = $json->read()) {
            if ((time() > $stopTime) && ($json->currentLine > $nbCron)) {
                return $json->currentLine;
            }
            
            try {
                $this->timer->start('getById');
                $obj = DataObject::getById($item['idPriceSell']);
                $this->timer->stop('getById');
                $obj->setArchive(1);
                $obj->setPublished(0);
                $this->timer->start('save');
                $obj->save(['versionNote' => 'EcGinkoia ('.__FUNCTION__.') :' . __LINE__]);
                $this->timer->stop('save');
            } catch (Exception $e) {
                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur archivage PriceSelling ('.$item['idPriceSell'].') : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile());
            }
        }

        return true;
    }

    public function updateDeclinaison(int $idPim, array $tab_info)
    {
        $connector = new connector();
        $diffusion = $connector->getDiffusion();
        $config_DeclinaisonUpdate = json_decode(Outils::getConfigByName($diffusion, 'ecGinkoiaERPDeclinaisonUpdate'), true);

        if (empty($config_DeclinaisonUpdate)) {
            return true;
        }
        
        $updateFields = [];
        $obj = DataObject::getById($idPim);
        $lstDiff = Outils::compareJson($tab_info, $obj->getObjectVars());
        foreach ($tab_info as $key => $value) {
            if (!in_array($key, $config_DeclinaisonUpdate)) { // la propriété à modifier n'est pas dans la config
                continue;
            }
            if (!in_array($key, $lstDiff)) { // la propriété à modifier est déjà correctement renseigné
                continue;
            }

            $method = 'set'.$key;
            if (method_exists($obj, $method)) {
                $obj->{$method}($value);
                $updateFields[$key] = $value;
            }
        }

        if ($updateFields) {
            $obj->save(['versionNote' => 'ecGinkoia ' . __LINE__]);
        }

        return true;
    }

    public function updateProduct(int $idPim, array $tab_info)
    {
        $connector = new connector();
        $diffusion = $connector->getDiffusion();
        $config_ProductUpdate = json_decode(Outils::getConfigByName($diffusion, 'ecGinkoiaERPProductUpdate'), true);

        if (empty($config_ProductUpdate)) {
            return true;
        }
        
        $updateFields = [];
        $obj = DataObject::getById($idPim);
        $lstDiff = Outils::compareJson($tab_info, $obj->getObjectVars());
        foreach ($tab_info as $key => $value) {
            if (!in_array($key, $config_ProductUpdate)) { // la propriété à modifier n'est pas dans la config
                continue;
            }
            if (!in_array($key, $lstDiff)) { // la propriété à modifier est déjà correctement renseigné
                continue;
            }

            $method = 'set'.$key;
            if (method_exists($obj, $method)) {
                $obj->{$method}($value);
                $updateFields[$key] = $value;
            }
        }

        if ($updateFields) {
            $obj->save(['versionNote' => 'ecGinkoia ' . __LINE__]);
        }

        return true;
    }

    public function forceUpdateStock()
    {
        $idPimProduct = 68527;
        $lstDecli = [68515, 68517, 68519];
        $prix = rand(1, 50);
        $stock = 3;

        $connector = new connector();
        $diffusion = $connector->getDiffusion();
        $idPimEntrepot =  Outils::getObjectByCrossId('95033', 'entrepot', $diffusion);
        $date_U = Carbon::parse(date('Y-m-d H:i:s'))->format('U');

        try {
            foreach ($lstDecli as $idPimDecli) {
                $stock += 2;
                $id_mvt = Outils::addMouvementStock(
                    $idPimProduct,
                    $idPimDecli,
                    '=', // Delta => +/-/=
                    0, // Type de stock => 0 pour Physique // 1 pour Réserve // 2 pour Attente
                    0,
                    $stock,
                    $idPimEntrepot, // Entrepot
                    $diffusion, // Diffusion
                    0, // Source
                    '', // Emplacement
                    'MAJ from '.$connector->diffusion_name,
                    $prix, // Prix
                    $date_U
                );

                Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - MAJ Stock ('.$idPimProduct.'-'.$idPimDecli.') : mouvement -> ' . $id_mvt . ', stock '.$stock, 2);
            }
        } catch (Exception $e) {
            Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur MAJ Stock ('.$idPimProduct.'-'.$idPimDecli.') : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile(), 3, '', 'error_save');
        }

        return true;
    }

    public function productIsActive($idPimProduct, $diffusion)
    {
        $obj = DataObject::getById($idPimProduct);
        if ('product' != $obj->getClassName()) {
            return false; // Ce n'est pas un produit
        }

        if (Outils::hasTag($obj, 'deref')) {
            Outils::deleteTag($obj, 'deref');
            if (0 == $obj->getPublished()) { // Le produit est déjà actif
                $obj->setPublished(true);

                try {
                    $obj->save(['versionNote' => 'OUTILS ' . __LINE__]);
                } catch (Exception $e) {
                    Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - Erreur : ' . $e->getMessage() . ' in line ' . $e->getLine() . ' of file ' . $e->getFile(), 3, $obj, 'error_save');
                }
            }
        }

        return true;
    }

    public static function arrayToHtmlTable(array $array, bool $separate_header = false, string $table_id = null, string $tr_class = null, string $td_class = null, string $th_class = null, string $css = null)
    {
        $table = '<table' . ($table_id ? ' id="'.$table_id.'"':'') . '>';
        $tr = '<tr' . ($tr_class ? ' class="'.$tr_class.'"':'') . '>';
        $td = '<td' . ($td_class ? ' class="'.$td_class.'"':'') . '>';
        $th = '<th' . ($th_class ? ' class="'.$th_class.'"':'') . '>';
        $style = $css ? ((0 === stripos($css, '<style')) ? $css : '<style>' . $css . '</style>') : '';

        $header = $footer = '';
        if ($separate_header) {
            $first_line = reset($array);
            if (range(0, count($first_line) - 1) == array_keys($first_line)) {
                $first_line = array_shift($array);
            } else {
                $first_line = array_keys($first_line);
            }
            $header = '<thead>'. $tr . $th . implode('</th>' . $th, $first_line) . '</th></tr></thead><tbody>';
            $footer = '</tbody>';
        }

        return $style . $table . $header . $tr . $td . self::arrayToCsv($array, '</td>' . $td, '</td></tr>' . $tr . $td) . '</td></tr>' . $footer . '</table>';
    }
    
    public static function arrayToCsv(array $array, string $fs = ';', string $ls = "\n", string $fe = '')
    {
        return $fe . implode($fe . $ls . $fe, array_map('implode', array_fill(0, count($array), $fe . $fs . $fe), $array)) . $fe;
    }
            

    public function devArthur($action, $data = [])
    {
        $timer_key = __FUNCTION__;
        $this->timer->start($timer_key);
        $connector = new connector();
        $diffusion = $connector->getDiffusion();

        // return Outils::getExist('3663983362175', '', 'ean13', 'declinaison');

        //https://devpim.midpim.com/launchecGinkoia?class=ecProduct&name=devArthur&param[action]=getFile&param[data][nbCron]=55
        if ('getFile' == $action) {
            $ret = true;
            foreach (range(0, 15) as $i) {
                $data['nbCron'] = $i;
                $retour = $this->cronGetFile($data);
                $this->collect->addInfo('Retour : '.json_encode($retour));
                $ret &= (is_numeric($retour) ? true : ((bool) $retour));

                if (is_bool($retour)) {
                    break;
                }
            }

            return $ret;
        }
        if ('getFileStock' == $action) {
            $ret = true;
            foreach (range(0, 15) as $i) {
                $data['nbCron'] = $i;
                $retour = $this->cronGetFileStock($data);
                $this->collect->addInfo('Retour : '.json_encode($retour));
                $ret &= (is_numeric($retour) ? true : ((bool) $retour));

                if (is_bool($retour)) {
                    break;
                }
            }

            return $ret;
        }
        if ('getFilePrice' == $action) {
            $ret = true;
            foreach (range(0, 15) as $i) {
                $data['nbCron'] = $i;
                $retour = $this->cronGetFilePrice($data);
                $this->collect->addInfo('Retour : '.json_encode($retour));
                $ret &= (is_numeric($retour) ? true : ((bool) $retour));

                if (is_bool($retour)) {
                    break;
                }
            }

            return $ret;
        }

        // Catalog
        if ('fillCatalog' == $action) {
            $retour = $this->cronFillCatalog($data);
            return $retour;
        }
        if ('fluctuMinus' == $action) {
            $retour = $this->cronUpdateFluctuMinus($data);
            return $retour;
        }
        if ('fluctuMinusFast' == $action) {
            $retour = $this->cronUpdateFluctuMinusFast($data);
            return $retour;
        }
        
        // Stock
        if ('updateStock' == $action) {
            $retour = $this->cronUpdateStock($data);
            return $retour;
        }
        if ('updateStockFast' == $action) {
            $retour = $this->cronUpdateStockFast($data);
            return $retour;
        }
        if ('updateEntrepot' == $action) {
            $retour = $this->cronUpdateEntrepot($data);
            return $retour;
        }

        // Price
        if ('updatePrice' == $action) {
            $retour = $this->cronUpdatePrice($data);
            return $retour;
        }

        if ('updatePriceFast' == $action) {
            $retour = $this->cronUpdatePriceFast($data);
            return $retour;
        }

        if ('updatePromo' == $action) {
            $retour = $this->cronUpdatePromo($data);
            return $retour;
        }

        if ('cleanPrice' == $action) {
            $retour = $this->cronCleanPrice($data);
            return $retour;
        }
        
        // Sync
        if ('syncGinkoia' == $action) {
            $retour = $this->cronSyncGinkoia([]);
            return $retour;
        }
        

        // Test
        if ('forceUpdateStock' == $action) {
            $retour = $this->forceUpdateStock();
            return $retour;

        }
        if ('forceDeleteProduct' == $action) {
            $api = new ShopifyApiClient();
            $product = new Product(68442);
            $retour = $api->deleteProduct($product);
            return $retour;

        }

        return true;
    }
}