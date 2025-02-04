<?php

namespace bundles\ecGinkoiaBundle\Controller;

use bundles\ecMiddleBundle\Controller\ecMiddleController;
use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\DataObject\Data\ObjectMetadata;
use Pimcore\Model\DataObject;
use Pimcore\Model\WebsiteSetting;
use Pimcore\Model\DataObject\Folder;
use bundles\ecGinkoiaBundle\src\connector;

class InstallController extends FrontendController
{
    /**
     * @Route("/ecGinkoia/install")
     */
    public function indexAction(Request $request)
    {
        if (!Dataobject::getByPath('/Config/ecGinkoia/')) {
            \Pimcore\Model\DataObject\Folder::create(['key' => 'ecGinkoia', 'path' => '/Config/ecGinkoia/', 'ParentId' => WebsiteSetting::getByName('folderConfig')->getData()]);
        }
        $parent = Dataobject::getByPath('/Config/ecGinkoia/')->getID();

        if (!Dataobject::getByPath('/ecGinkoia/')) {
            \Pimcore\Model\DataObject\Folder::create(['key' => 'ecGinkoia', 'path' => '/ecGinkoia/', 'ParentId' => Folder::getByPath('/')->getId()]);
        }
        $folder = Folder::getByPath('/ecGinkoia/');
        
        //diffusion
        $connector = new connector(true);
        $connector->getMyDiffusion();
        $connector->getDiffusion();
        $retourConfig = $this->initConfigValues($connector);
        $retourActions = $this->initActions($connector);
        $retourCrons = $this->initCrons($connector);
        

        return new JsonResponse(['Config' => 'OK', 'retourConfig' => $retourConfig, 'retourActions' => $retourActions, 'retourCrons' => $retourCrons], 200);
    }
    
    /**
     * @param bundles\ecGinkoiaBundle\src\connector $connector
     * @return bool
     */
    public function initConfigValues(connector $connector)
    {
        $diff = $connector->getMyDiffusion();
        $middleController = new ecMiddleController();

        $tabRef = [
            [
                'key' => 'CODE_CHRONO',
                'value' => 'CODE_CHRONO',
            ],
            [
                'key' => 'CODE_FOURN',
                'value' => 'CODE_FOURN',
            ],
        ];
                
        // Choix de la référence du produit
        if (!DataObject::getByPath('/Diffusion/ecGinkoia/ecGinkoiaProductReference')) {
            $config = new DataObject\Config();
            $config->setParentID($diff->getId())
                ->setKey('ecGinkoiaProductReference')
                ->setIdconfig('ecGinkoiaProductReference')
                ->setName('Définir le champ à utiliser pour renseigner la référence produit')
                ->setValeur('CODE_CHRONO')
                ->setPublished(true)
                ->setTypeList(json_encode($tabRef))
                ->setTypeConfig('select')
                ->save($connector->getMySign(__LINE__));
        }
        // Choix de la référence des déclinaisons
        if (!DataObject::getByPath('/Diffusion/ecGinkoia/ecGinkoiaCombinationReference')) {
            $config = new DataObject\Config();
            $config->setParentID($diff->getId())
                ->setKey('ecGinkoiaCombinationReference')
                ->setIdconfig('ecGinkoiaCombinationReference')
                ->setName('Définir le champ à utiliser pour renseigner la référence déclinaison')
                ->setValeur('CODE_FOURN')
                ->setPublished(true)
                ->setTypeList(json_encode($tabRef))
                ->setTypeConfig('select')
                ->save($connector->getMySign(__LINE__));
        }
        foreach (range(1, 5) as $i) {
            // Classement
            if (!DataObject::getByPath('/Diffusion/ecGinkoia/ecGinkoiaClassement'.$i)) {
                $config = new DataObject\Config();
                $config->setParentID($diff->getId())
                    ->setKey('ecGinkoiaClassement'.$i)
                    ->setIdconfig('ecGinkoiaClassement'.$i)
                    ->setName('Remonter le Classement ('.$i.') en caractéristique produit')
                    ->setValeur('')
                    ->setPublished(true)
                    ->setTypeConfig('input')
                    ->save($connector->getMySign(__LINE__));
            }
        }
        
        // Utiliser le fichier des nomenclatures (Catégorie)
        if (!DataObject::getByPath('/Diffusion/ecGinkoia/ecGinkoiaUseNomenk')) {
            $config = new DataObject\Config();
            $config->setParentID($diff->getId())
                ->setKey('ecGinkoiaUseNomenk')
                ->setIdconfig('ecGinkoiaUseNomenk')
                ->setName('Utiliser le fichier des nomenclatures (Catégorie)')
                ->setValeur(0)
                ->setPublished(true)
                ->setTypeConfig('checkbox')
                ->save($connector->getMySign(__LINE__));
        }
        // Utiliser le fichier des nomenclatures secondaires (Catégorie)
        if (!DataObject::getByPath('/Diffusion/ecGinkoia/ecGinkoiaUseArtNomenk')) {
            $config = new DataObject\Config();
            $config->setParentID($diff->getId())
                ->setKey('ecGinkoiaUseArtNomenk')
                ->setIdconfig('ecGinkoiaUseArtNomenk')
                ->setName('Utiliser le fichier des nomenclatures secondaires (Catégorie)')
                ->setValeur(0)
                ->setPublished(true)
                ->setTypeConfig('checkbox')
                ->save($connector->getMySign(__LINE__));
        }
        // Utiliser le fichier des EAN13 secondaires
        if (!DataObject::getByPath('/Diffusion/ecGinkoia/ecGinkoiaUseCbFourn')) {
            $config = new DataObject\Config();
            $config->setParentID($diff->getId())
                ->setKey('ecGinkoiaUseCbFourn')
                ->setIdconfig('ecGinkoiaUseCbFourn')
                ->setName('Utiliser le fichier des EAN13 secondaires')
                ->setValeur(0)
                ->setPublished(true)
                ->setTypeConfig('checkbox')
                ->save($connector->getMySign(__LINE__));
        }
        // Utiliser le fichier des couleurs statistiques
        if (!DataObject::getByPath('/Diffusion/ecGinkoia/ecGinkoiaUseCouleurStat')) {
            $config = new DataObject\Config();
            $config->setParentID($diff->getId())
                ->setKey('ecGinkoiaUseCouleurStat')
                ->setIdconfig('ecGinkoiaUseCouleurStat')
                ->setName('Utiliser le fichier couleurs statistiques')
                ->setValeur(0)
                ->setPublished(true)
                ->setTypeConfig('checkbox')
                ->save($connector->getMySign(__LINE__));
        }

        return true;

        // Choisir les champs de la class Product dont l'ERP est maitre
        $config = Dataobject::getByPath('/Diffusion/ecGinkoia/ecGinkoiaERPProductUpdate');
        if (!$config) {
            $config = new DataObject\Config();
            $config->setValeur('');
        }
        $champs = $middleController->getDefinitionFull('product');
        $tabCl = [];
        foreach ($champs as $chp) {
            $tabCl[] = ['key' => $chp['name'], 'value' => $chp['entete']];
        }
        $config->setParentID($diff->getId());
        $config->setKey('ecGinkoiaERPProductUpdate');
        $config->setIdconfig('ecGinkoiaERPProductUpdate');
        $config->setPublished(true);
        $config->setName('Choisir les champs de la class Product dont l\'ERP est maitre');
        $config->setTypeList(json_encode($tabCl));
        $config->setTypeConfig('select_multiple');
        $config->save($connector->getMySign(__LINE__));

        
        // Choisir les champs de la class Declinaison dont l'ERP est maitre
        $config = Dataobject::getByPath('/Diffusion/ecGinkoia/ecGinkoiaERPDeclinaisonUpdate');
        if (!$config) {
            $config = new DataObject\Config();
            $config->setValeur('');
        }
        $champs = $middleController->getDefinitionFull('declinaison');
        $tabCl = [];
        foreach ($champs as $chp) {
            $tabCl[] = ['key' => $chp['name'], 'value' => $chp['entete']];
        }
        $config->setParentID($diff->getId());
        $config->setKey('ecGinkoiaERPDeclinaisonUpdate');
        $config->setIdconfig('ecGinkoiaERPDeclinaisonUpdate');
        $config->setPublished(true);
        $config->setName('Choisir les champs de la class Declinaison dont l\'ERP est maitre');
        $config->setTypeList(json_encode($tabCl));
        $config->setTypeConfig('select_multiple');
        $config->save($connector->getMySign(__LINE__));


        return true;
    }
    
    /**
     * @param bundles\ecGinkoiaBundle\src\connector $connector
     * @return bool
     */
    public function initActions(connector $connector)
    {
        if (!Dataobject::getByPath('/Action/updateOrderHistory ecGinkoia')) {
            $action = new DataObject\Action();
            $action->setParentID(WebsiteSetting::getByName('folderAction')->getData());
            $action->setKey('updateOrderHistory ecGinkoia');
            $action->setName('updateOrderHistory ecGinkoia');
            $action->setAction(array('\bundles\ecGinkoiaBundle\Controller\webhookController::hookUpdateOrderHistory'));
            $action->setDescription('updateOrderHistory ecGinkoia');
            $action->setPublished(true);
            $action->save();
            $Hook = Dataobject::getByPath('/Hook/updateOrderHistory');
            $this->addActionid($Hook, $action);
        }

        return true;
    }

    public function addActionid($object, $actionObj)
    {
        $action = new ObjectMetadata('action', ['active', 'position', ], $actionObj);
        $listAction = $object->getAction();
       
 
        $cpt = 1;
        foreach ($listAction as $elementData) {
            if ($elementData->getObjectId() != $actionObj->getId()) {          
                $new_actionids[] = $elementData;
                $cpt++;
            }
        }
 
        $action->setElement($actionObj);
        $action->setData(
            [
                'active' => 1,
                'position' => $cpt,
            ]
        );
       
        $new_actionids[] = $action;
       
        //replace old ones by new set and save
        $object->setAction($new_actionids);
        \Pimcore\Model\Version::disable();
        $object->save();
        \Pimcore\Model\Version::enable();
        return true;
    }
    
    /**
     * @param bundles\ecGinkoiaBundle\src\connector $connector
     * @return bool
     */
    public function initCrons(connector $connector)
    {
        $diff = $connector->getMyDiffusion();
        $id_folder_cron = WebsiteSetting::getByName('folderCron')->getData();

        // Crons de MAJ du Pim
        if (!Dataobject::getByPath('/Cron/ecGinkoia_MAJ')) {
            $cron = new DataObject\Cron();
            $cron->setKey('ecGinkoia_MAJ')
                ->setPrefix('ecGinkoia_MAJ');
        } else {
            $cron = Dataobject::getByPath('/Cron/ecGinkoia_MAJ');
        }
        $cron->setParentID($id_folder_cron)
            ->setCommentaire('MAJ du catalogue, stock et prix de Ginkoia vers Middle')
            ->setListStages('MAJGinkoia')
            ->setToken('TOKENGINKO')
            ->setPublished(true)
            ->setStages(['\bundles\ecGinkoiaBundle\src\ecProduct::cronGetFile','\bundles\ecGinkoiaBundle\src\ecProduct::cronFillCatalog','\bundles\ecGinkoiaBundle\src\ecProduct::cronUpdateStock',])
            ->save($connector->getMySign(__LINE__));


        // Crons de récupération et dépôt des fichiers
        if (!Dataobject::getByPath('/Cron/ecGinkoia_sync')) {
            $cron = new DataObject\Cron();
            $cron->setKey('ecGinkoia_sync')
                ->setPrefix('ecGinkoia_sync');
        } else {
            $cron = Dataobject::getByPath('/Cron/ecGinkoia_sync');
        }
        $cron->setParentID($id_folder_cron)
            ->setCommentaire('Syncronisation des fichiers de Ginkoia')
            ->setListStages('SyncGinkoia')
            ->setToken('TOKENGINKO')
            ->setPublished(true)
            ->setStages(['\bundles\ecGinkoiaBundle\src\ecProduct::cronSyncGinkoia'])
            ->save($connector->getMySign(__LINE__));

        return true;
    }
    
}
