<?php

namespace bundles\ecGinkoiaBundle\src;

use bundles\ecGinkoiaBundle\src\ecUtils;
use bundles\ecGinkoiaBundle\src\ecTimer;
use bundles\ecGinkoiaBundle\src\ecCollect;
use bundles\ecMiddleBundle\Services\Outils;
use bundles\ecMiddleBundle\Services\Hook;
use bundles\ecMiddleBundle\Services\DbFile;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\WebsiteSetting;
use Pimcore\Tool;
use Carbon\Carbon;
use Exception;
use ErrorException;

class connector
{
    /**
     * @var string
     */
    private $diffusion_key = 'ecGinkoia';
    /**
     * @var string
     */
    public $diffusion_name;
    /**
     * '/Diffusion/SmithersGinkoia' alors que cela devrait être '/Diffusion'
     * @var string
     */
    private $diffusion_path;
    /**
     * @var Pimcore\Model\DataObject\Diffusion
     */
    private $diffusion;
    /**
     * @var string
     */
    private $my_sign = 'ecGinkoia';
    /**
     * @var array
     */
    private $a_iso = [];
    /**
     * @var bundles\ecGinkoiaBundle\src\ecTimer
     */
    public $timer;
    /**
     * @var bundles\ecGinkoiaBundle\src\ecCollect
     */
    private $collect;


    /**
     * @param bool $minimal
     * @return $this
     */
    public function __construct(bool $minimal = false)
    {
        $this->timer = ecTimer::get();
        $this->collect = ecCollect::get();
        $this->a_iso = Tool::getValidLanguages(); // semble poser problème à l'install

        if (!$minimal) {
            $this->getDiffusion();
        }

        return $this;
    }

    /**
     * build my diffusion if not already done
     * @return Pimcore\Model\DataObject\Diffusion
     */
    public function getMyDiffusion()
    {
        //dossier arbo
        if (!Folder::getByPath('/Category/CategoryDiffusion/'.$this->diffusion_key)) {
            $this->collect->addInfo('On doit créer le dossier arborescent pour la diffusion '.$this->diffusion_key);
            $parent = Folder::getByPath('/Category/CategoryDiffusion');
            $categoryFolder = new Folder();
            $categoryFolder->setParentID($parent->getId());
            $categoryFolder->setKey($this->diffusion_key);
            $categoryFolder->save($this->getMySign(__LINE__));
            $this->collect->addInfo('On a créé le dossier arborescent pour la diffusion '.$this->diffusion_key);
        } else {
            // $this->collect->addInfo('On récupère le dossier arborescent pour la diffusion '.$this->diffusion_key);
            $categoryFolder = Folder::getByPath('/Category/CategoryDiffusion/'.$this->diffusion_key);
        }
        //diffusion
        if (!DataObject::getByPath('/Diffusion/'.$this->diffusion_key)) {
            $this->collect->addInfo('On doit créer la diffusion '.$this->diffusion_key);
            $folderDiffusionId = Outils::getWebSetting('folderDiffusion');
            $diff = new DataObject\Diffusion();
            $diff->setParentID($folderDiffusionId);
            $diff->setKey($this->diffusion_key);
            $diff->setName($this->diffusion_key);
            $diff->setId_folder($categoryFolder->getId());
            $diff->setPublished(true);
            $diff->save($this->getMySign(__LINE__));
            $this->collect->addInfo('On a créé la diffusion '.$this->diffusion_key);
        } else {
            // $this->collect->addInfo('On récupère la diffusion '.$this->diffusion_key);
            $diff = DataObject::getByPath('/Diffusion/'.$this->diffusion_key);
        }
        //maj id de dossier arbo si pas fait
        if (empty($diff->getId_folder())) {
            $this->collect->addInfo('On doit mettre à jour le dossier arborescent de la diffusion '.$this->diffusion_key);
            $diff->setId_folder($categoryFolder->getId());
            $diff->save($this->getMySign(__LINE__));
            $this->collect->addInfo('On a mis à jour le dossier arborescent de la diffusion '.$this->diffusion_key);
        }

        return $diff;
    }

    /**
     * find a Diffusion from configuration name
     * if more than one, select the one in the good path
     * if none, create our own (!!!)
     * if path does not exist, reject because we are not allowed to create one
     *
     * @return Pimcore\Model\DataObject\Diffusion
     */
    public function getDiffusion()
    {
        $timer_key = __FUNCTION__;
        $this->timer->start($timer_key);
        if (!empty($this->diffusion)) {
            $this->timer->stop($timer_key);
            return $this->diffusion;
        }

        $wss = Outils::getWebSetting('folderDiffusion');
        if (!$wss) {
            throw new Exception('Please configure unconfigured key folderDiffusion');
        }
        $folderDiffusionId = $wss;
        if (!$folderDiffusion = Outils::getCache('object_' . $folderDiffusionId)) {
            $folderDiffusion = DataObject::getById($folderDiffusionId);
            Outils::putCache('object_' . $folderDiffusionId, $folderDiffusion);
        }
       
        // $folderDiffusion = DataObject::getById($folderDiffusionId);

        $key = $folderDiffusion->getPath() . $folderDiffusion->getKey() . '/' . $this->diffusion_key;
        // $this->diffusion = DataObject::getByPath($this->diffusion_path);
        if (!$this->diffusion = Outils::getCache('object_' . $key)) {
            $this->diffusion = DataObject::getById($key);
            Outils::putCache('object_' . $key, $this->diffusion);
        }
        //verify existence of object
        if (!is_object($this->diffusion)) {
            $this->diffusion = $this->getMyDiffusion();
        }

        //verify class of object
        if ('diffusion' != $this->diffusion->getClassName()) {
            throw new Exception('The object at path ' . $this->diffusion_path . ' is not of class diffusion');
        }

        $this->diffusion_name = $this->diffusion->getName('fr');

        $this->timer->stop($timer_key);
        return $this->diffusion;
    }

    public function getMySign(int $line, string $comment = '')
    {
        return ['versionNote' => vsprintf('%s l%s %s', [$this->my_sign, $line, $comment])];
    }

    /**
     * récupération du flux en dbfile
     * @var string $endpoint
     * @var string $target_dbfile
     * @var array $options : TODO options à spécifier, a priori le classique pour la DbFile mais peut-être plus
     * @return bool le collect a aussi des infos
     */
    public function getFile(string $endpoint, string $target_dbfile, array $options = [])
    {
        return true;
        $timer_key = __FUNCTION__ . '_' . $endpoint;
        set_error_handler([$this, 'exception_error_handler']);
        $tz = 'Europe/Paris';
        $this->timer->start($timer_key);

        //connexion
        $this->timer->stop($timer_key);
        $this->getDiffusion();
        $this->connectToErp();
        $this->timer->start($timer_key);
        if (!$this->getConnected()) {
            $this->collect->addError(vsprintf('On est pas connexté à %s', [$this->diffusion_name]));
            $this->timer->stop($timer_key);
            return false;
        }

        //récup date dernière requête
        $date_requete = Carbon::now($tz)->format('Y-m-d\\TH:i:s');

        //récup contenu
        $this->timer->stop($timer_key);
        $this->timer->start('getEndpoint_'.$endpoint);
        $content_raw = $this->conn->setEndpointLink($endpoint)->getEndpoint($options['req'] ?? '');
        $this->collect->addInfo($this->conn->getEndpointLink());
        $this->timer->stop('getEndpoint_'.$endpoint);
        $this->timer->start($timer_key);
        $content = $content_raw['value'] ?? null;
        $required = $options['required'] ?? false;
        if (empty($content)) {
            $this->collect->addWarning(vsprintf('Le contenu du flux %s %s est vide', [$endpoint, $this->diffusion_name]));
            if (!empty($content_raw['error'])) {
                if (!empty($content_raw['error']['code'])) {
                    $this->collect->addError(vsprintf('Erreur de flux %s, code : %s', [$endpoint, $content_raw['error']['code']]));
                }
                if (!empty($content_raw['error']['message'])) {
                    $this->collect->addError(vsprintf('Erreur de flux %s, message : %s', [$endpoint, $content_raw['error']['message']]));
                }
            }
            $this->timer->stop($timer_key);
            if ($required) {
                return false;
            }
            return true;
        }
        $this->collect->addInfo(vsprintf('flux %s %s récupéré, %s items', [$endpoint, $this->diffusion_name, count($content)]));
        
        //conversion DbFile
        try {
            $this->timer->stop($timer_key);
            $this->timer->start('buildFromArray_'.$endpoint);
            $ret = DbFile::buildFromArray($content, $target_dbfile, $options['continue']??false, $options['icallback']??null, $options['fieldsDef']??[], $options['upd_field']??null);
            $this->timer->stop('buildFromArray_'.$endpoint);
            $this->timer->start($timer_key);
            $this->collect->addInfo(vsprintf('on a %s éléments dans le flux %s et %s enregistrés en DbFile %s', [count($content), $endpoint, $ret, $target_dbfile]));
        } catch (Exception $e) {
            $this->collect->addError(vsprintf($e->getMessage(), []));
            $this->timer->stop($timer_key);
            return false;
        }
        $this->timer->stop($timer_key);

        return true;
    }

    /**
     * test du getFile avec paramètres influant sur la requête et le DbFile
     */
    public function testGetFile1()
    {
        $endpoint = 'contact';
        $target_dbfile = $this->diffusion_key.'contact';
        $options = [
            'req' => '?$filter=type eq \'Person\'',                                     //fonctionnel
            'icallback' => [get_class($this), 'dbFileCallbackTest'],                    //fonctionnel
            'fieldsDef' => ['@odata.etag' => ['name'=>'@odata.etag', 'type' => 'text']],//fonctionnel
        ];

        return $this->getFile($endpoint, $target_dbfile, $options);
    }

    public static function dbFileCallbackTest($item)
    {
        if (empty($item['number']) || !preg_match('/^CT/', $item['number'])) {
            return false;
        }

        return $item;
    }

    public function exception_error_handler($severity, $message, $file, $line)
    {
        if (!(error_reporting() & $severity)) {
            // Ce code d'erreur n'est pas inclu dans error_reporting

            return;
        }
        throw new ErrorException($message . ' on line ' . $line . ' of file ' . $file, 0, $severity, $file, $line);
    }
}