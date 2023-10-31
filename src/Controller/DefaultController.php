<?php

namespace bundles\ecGinkoiaBundle\Controller;

use bundles\ecMiddleBundle\Services\Outils;
use Exception;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\{Address, Diffusion, Folder, Order};
use Pimcore\Model\WebsiteSetting;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/ec_ginkoia')]
class DefaultController extends FrontendController
{
    const DIFFUSION_PATH = '/Diffusion/Ginkoia';
    const DIFFUSION_NAME = 'Ginkoia';
    const PARAMETERS = [
        'active' => 'Activer le connecteur',
        'tva' => 'tva',
        'mag_id' => 'mag_id',
        'filterImg' => 'Filter products without image',
        'which_product_reference' => 'which_product_reference',
        'which_combination_reference' => 'which_combination_reference',
        'use_nomenk' => 'use_nomenk',
        'use_artnomenk' => 'use_artnomenk',
        'use_cbfourn' => 'use_cbfourn',
        'use_couleur_stat' => 'use_couleur_stat',
        'sw_oneDeclInOneProd' => 'sw_oneDeclInOneProd',
        'sw_splitColor' => 'sw_splitColor',
        'sw_shippingCostInOrder' => 'sw_shippingCostInOrder',
        'codearticle_emballage' => 'codearticle_emballage',
        'ftp_host' => 'Hote FTP',
        'ftp_user' => 'Utilisateur FTP',
        'ftp_pass' => 'Mot de passe FTP',
        'ftp_path_stock' => 'ftp_path_stock',
        'ftp_path_catalog' => 'ftp_path_catalog',
        'promo_percent' => 'promo_percent',
        'info_export_com' => 'info_export_com',
        'cust_export_com' => 'cust_export_com',
        'id_generate' => 'id_generate',
        'id_shipped' => 'id_shipped',
        'add_features' => 'add_features',
        'classement1' => 'classement1',
        'classement2' => 'classement2',
        'classement3' => 'classement3',
        'classement4' => 'classement4',
        'classement5' => 'classement5',
        'sw_addColorInFeat' => 'sw_addColorInFeat',
        'conventionRef' => 'conventionRef',
        'conventionColor' => 'conventionColor',
    ];


    #[Route('/test')]
    public function indexAction(Request $request): Response
    {
        // ouverture de files/INIT_ARTWEB_4.TXT
        $object = $this->ginkoiaToPimcore();
        return new Response('<pre>---<br>' . json_encode($object) . '---<br></pre>');
    }

    public function ginkoiaToPimcore()
    {
        $dataList = $this->parseCSVFile(filePath: __DIR__ . '/../../files/INIT_ARTWEB_4.TXT');
        // clés de la liste des données : CODE_ARTICLE;CODE_MODELE;CODE_NK;IDMARQUE;MARQUE;CODE_FOURN;PRODUIT;COULEUR;GCS_ID;TAILLE;TGF_ID;TVA;GENRE;CLASSEMENT1;CLASSEMENT2;CLASSEMENT3;CLASSEMENT4;CLASSEMENT5;COLLECTION;WEB_DETAIL;WEB_COMPOSITION;POIDS;POIDSL;CODE_CHRONO;ARCHIVER;CODE_COULEUR;WEB;PREVENTE;QTE_PREVENTE;DELAIS_PREVENTE;ETAT_DATA;CODE_EAN
        foreach ($dataList as $data) {
            if (!$product = DataObject::getByPath('/Product/'.$data['CODE_ARTICLE'])){
                $product = new DataObject\Product();
            }
            // CODE_ARTICLE
            $product->setReference($data['CODE_ARTICLE']);
            // CODE_MODELE
            // CODE_NK
            // IDMARQUE
//            Outils::putCreateMarque();

            // MARQUE
//            if (!$manufacturer = DataObject::getByPath('/Marque/'.$marque)) {
//                $manufacturer = new DataObject\Marque();
//                $manufacturer->setName($marque);
//                $manufacturer->setKey($marque);
//                $manufacturer->setParentId(parentId: DataObject::getByPath(path: '/Marque')->getId());
//                $manufacturer->save();
//            }
//            $product->setManufacturer($manufacturer);
            // CODE_FOURN
            // PRODUIT
            $product->setName($data['PRODUIT']);
            // COULEUR
            // GCS_ID
            // TAILLE
            // TGF_ID
            // TVA
//            if (!$tax_default = DataObject::getByPath('/Tax/'.$tva)) {
//                $tax_default = new DataObject\Tax();
//                $tax_default->setKey($tva);
//                $tax_default->setName($tva);
//                $tax_default->setParentId(parentId: DataObject::getByPath(path: '/Product')->getId());
//                $tax_default->save();
//            }
//            $product->setTax_default($tax_default);
            // GENRE
            // CLASSEMENT1
            // CLASSEMENT2
            // CLASSEMENT3
            // CLASSEMENT4
            // CLASSEMENT5
            // COLLECTION
            // WEB_DETAIL
            // WEB_COMPOSITION
            // POIDS
            // POIDSL
            // CODE_CHRONO
            // ARCHIVER
            // CODE_COULEUR
            // WEB
            // PREVENTE
            // QTE_PREVENTE
            $product->setQuantity($data['QTE_PREVENTE']);
            // DELAIS_PREVENTE
            // ETAT_DATA
            // CODE_EAN
//            $product->setEan13($code_ean);
            $product->setParentId(parentId: DataObject::getByPath(path: '/Product')->getId());
            $product->setKey($data['CODE_ARTICLE']);
            $product->save();

        }
    }

    /**
     * @throws Exception
     */
    function parseCSVFile($filePath)
    {
        $csvData = array();

        if (($handle = fopen($filePath, "r")) !== false) {
            $header = fgetcsv($handle, 1000, ";"); // Lire les en-têtes
            while (($data = fgetcsv($handle, 1000, ";")) !== false) {
                if (count($data) === count($header)) {
                    $rowData = array_combine($header, $data);
                    $csvData[] = $rowData;
                } else {
                    // Gérer le cas où le nombre d'éléments ne correspond pas
                }
            }
            fclose($handle);
        } else {
            throw new Exception("Impossible d'ouvrir le fichier CSV.");
        }

        return $csvData;
    }

    public function orderCreateAction(Request $request, LoggerInterface $logger): Response
    {
        $diffusion = Diffusion::getByPath(path: self::DIFFUSION_PATH);
        $parent_id = DataObject::getByPath(path: '/Order')->getId();

        /** @var ShopifyOrder $orderData */
        $orderData = json_decode($request->getContent());
        // Récupération de la commande
        if (!$order = Outils::getObjectByCrossId(crossid: $orderData->id, class: 'order', diffusion: $diffusion)) {
            // Création de la commande
            $order = new Order();
        }


        // Récupération du client
        if (!$customer = Outils::getObjectByCrossId(crossid: $orderData->customer->id, class: 'customer', diffusion: $diffusion)) {
//             Adresse postale du client
            $postalAddress = new Address();
            $postalAddress->setAddress1(address1: $orderData->shipping_address->address1);
            $postalAddress->setAddress2(address2: $orderData->shipping_address->address2);
            $postalAddress->setCity(city: $orderData->shipping_address->city);
            $postalAddress->setPostcode(postcode: $orderData->shipping_address->zip);
            $postalAddress->setCountry(country: $orderData->shipping_address->country_code);
            $postalAddress->setPhone(phone: $orderData->shipping_address->phone);
            $postalAddress->setEmail(email: $orderData->customer->email);
            $postalAddress->setFirstname(firstname: $orderData->shipping_address->first_name);
            $postalAddress->setLastname(lastname: $orderData->shipping_address->last_name);
            $postalAddress->setCompany(company: $orderData->shipping_address->company);
            $postalAddress->setKey($orderData->customer->id);
            $postalAddress->save();
            $logger->info(message: 'orderCreateAction', context: [$postalAddress->getId()]);
            Outils::addCrossid(object: $postalAddress, source: $diffusion, ext_id: $orderData->customer->id);

            // Adresse de facturation
            $billingAddress = new Address();
            $billingAddress->setAddress1(address1: $orderData->billing_address->address1);
            $billingAddress->setAddress2(address2: $orderData->billing_address->address2);
            $billingAddress->setCity(city: $orderData->billing_address->city);
            $billingAddress->setPostcode(postcode: $orderData->billing_address->zip);
            $billingAddress->setCountry(country: $orderData->billing_address->country_code);
            $billingAddress->setPhone(phone: $orderData->billing_address->phone);
            $billingAddress->setEmail(email: $orderData->customer->email);
            $billingAddress->setFirstname(firstname: $orderData->billing_address->first_name);
            $billingAddress->setLastname(lastname: $orderData->billing_address->last_name);
            $billingAddress->setCompany(company: $orderData->billing_address->company);
            $billingAddress->setKey(o_key: $orderData->customer->id);
            $billingAddress->save();
            $logger->info(message: 'orderCreateAction', context: [$billingAddress->getId()]);
            Outils::addCrossid(object: $billingAddress, source: $diffusion, ext_id: $orderData->customer->id);

//             Création du client
            $customer = new Customer();
            $customer->setFirstname(firstname: $orderData->shipping_address->first_name);
            $customer->setLastname(lastname: $orderData->shipping_address->last_name);
            $customer->setEmail(email: $orderData->customer->email);
            $customer->setPhone(phone: $orderData->shipping_address->phone);
            $customer->setKey(o_key: $orderData->customer->id);
            $customer->save();
            $logger->info(message: 'orderCreateAction', context: [$customer->getId()]);
            Outils::addCrossid(object: $customer, source: $diffusion, ext_id: $orderData->customer->id);
        }
        $order->setParentId(parentId: $parent_id);
        $order->setAddress_delivery(address_delivery: $orderData->shipping_address->address1);
        $order->setAddress_invoice(address_invoice: $orderData->billing_address->address1);
        $order->setCustomer(customer: $customer);
        $order->setCreationDate(creationDate: $orderData->created_at);
        $order->setModificationDate(modificationDate: $orderData->updated_at);
        $order->setTotal_shipping(total_shipping: $orderData->current_subtotal_price);
        $order->setTotal_paid(total_paid: $orderData->total_price);
        $order->setTotal_paid_tax_incl(total_paid_tax_incl: $orderData->total_price);
        $order->setTotal_paid_tax_excl(total_paid_tax_excl: $orderData->total_price - $orderData->total_tax);
        $order->setTotal_paid_real(total_paid_real: $orderData->total_price);
        $order->setTotal_products(total_products: count($orderData->line_items));
        $order->setTotal_products_wt(total_products_wt: $orderData->total_weight);
        $order->setTotal_shipping_tax_incl(total_shipping_tax_incl: $orderData->total_tax);
        $order->setTotal_shipping_tax_excl(total_shipping_tax_excl: $orderData->total_tax);
        $order->setKey($orderData->id);
        $order->save();

        Outils::addCrossid(object: $order, source: $diffusion, ext_id: $orderData->id);

        $logger->info(message: 'orderCreateAction', context: [$order->getId()]);
        return new Response(content: '<pre>OK</pre>', status: 200);
    }


    /**
     * @throws \Exception
     */
    #[Route('/install')]
    public function installAction(Request $request): Response
    {
        if (!Dataobject::getByPath(self::DIFFUSION_PATH)) {
            $diffusion = new DataObject\Diffusion();
            $diffusion->setParentID(WebsiteSetting::getByName('folderDiffusion')->getData());
            $diffusion->setKey(self::DIFFUSION_NAME);
            $diffusion->setName(self::DIFFUSION_NAME);
            $diffusion->setPublished(true);
            $diffusion->save();
            $lstConfig = $diffusion->getConfig();
        } else {
            $diffusion = Dataobject::getByPath(self::DIFFUSION_PATH);
        }
        foreach (self::PARAMETERS as $key => $name) {
            //Nomenclature BL
            if (!Dataobject::getByPath(self::DIFFUSION_PATH . '/' . $key) && $key !== $name) {
                $config = new DataObject\Config();
                $config->setParentID($diffusion->getId());
                $config->setKey($key);
                $config->setIdconfig($key);
                $config->setPublished(true);
                $config->setName($name);
                switch ($key) {
                    case 'filterImg':
                    case 'active':
                        $config->setValeur(0);
                        $config->setTypeConfig('checkbox');
                        break;
                    default:
                        $config->setTypeConfig('input');
                        $config->setValeur('');
                        break;
                }
                $config->save();
            }
        }

        $diffusion->save();

        return new Response('<pre>ok</pre>');
    }
}
