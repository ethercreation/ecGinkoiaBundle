<?php
/**
 * Copyright (c) 2024.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to a commercial license from SARL Ether Création
 * Use, copy, modification or distribution of this source file without written
 * license agreement from the SARL Ether Création is strictly forbidden.
 * In order to obtain a license, please contact us: contact@ethercreation.com
 * ...........................................................................
 * INFORMATION SUR LA LICENCE D'UTILISATION
 *
 * L'utilisation de ce fichier source est soumise à une licence commerciale concédée par la société Ether Création
 * Toute utilisation, reproduction, modification ou distribution du present fichier source sans contrat de licence écrit de la part de la SARL Ether Création est expressément interdite.
 * Pour obtenir une licence, veuillez contacter la SARL Ether Création à l'adresse : contact@ethercreation.com
 * ...........................................................................
 *
 * @author    Théodore Riant <theodore@ethercreation.com>
 * @copyright 2008-2024 Ether Création SARL
 * @license   Commercial license
 * International Registered Trademark & Property of Ether Création SARL
 */

namespace bundles\ecGinkoiaBundle\Controller;

use bundles\ecMiddleBundle\Services\DbFile;
use bundles\ecMiddleBundle\Services\Outils;
use Exception;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\{Address,
    Attribut,
    Carac,
    Product,
    Client,
    Config,
    Declinaison,
    Diffusion,
    Entrepot,
    Marque,
    Paiement};
use Pimcore\Model\WebsiteSetting;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller qui fait toutes les actions nécessaires à la synchronisation entre Pimcore et Ginkoia
 */
#[Route('/ec_ginkoia')]
class DefaultController extends FrontendController
{
    /** @var string Chemin de la diffusion à utiliser */
    const DIFFUSION_PATH = '/Diffusion/Ginkoia';
    /** @var string Nom de la diffusion */
    const DIFFUSION_NAME = 'Ginkoia';
    /** @var string[] Paramètres du bundle, sera ajouté dans install si la clé est différente de sa valeur */
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
        'classement1' => 'Nom de la caractéristique "classement1"',
        'classement2' => 'Nom de la caractéristique "classement2"',
        'classement3' => 'Nom de la caractéristique "classement3"',
        'classement4' => 'Nom de la caractéristique "classement4"',
        'classement5' => 'Nom de la caractéristique "classement5"',
        'sw_addColorInFeat' => 'sw_addColorInFeat',
        'conventionRef' => 'conventionRef',
        'conventionColor' => 'conventionColor',
    ];
    /** @var string Nom du fichier des prix */
    const FILE_PRIX = 'INIT_PRIX_2';
    /** @var string Nom du fichier des stocks */
    const FILE_STOCK = 'INIT_STOCK_3';
    /** @var string Nom du fichier des articles */
    const FILE_ARTWEB = 'INIT_ARTWEB_4';
    /** @var string Dossier par défaut des catégories */
    const DEFAULT_CATEGORY_PATH = '/Category/CategoryDiffusion/default';

    /**
     * Récupère des fichiers et les traite dans le cadre d'une tâche cron.
     *
     * @param array $params Paramètres de la tâche cron.
     *                      - 'cron' : Indicateur de tâche cron.
     *                      - 'nbCron' : Nombre de lignes de tâche cron à traiter.
     *                      - 'stopTime' : Temps d'arrêt de la tâche cron.
     *
     * @return bool|int Retourne TRUE si la tâche est terminée, sinon le nombre de lignes de tâche cron traitées.
     */
    #[Route('/cron/get_file')]
    function cronGetFile(array $params = ['cron' => 0, 'nbCron' => 0, 'stopTime' => 0]): bool
    {
        // Extraction des paramètres
        ['nbCron' => $nbCron, 'stopTime' => $stopTime] = $params;

        // Tableau des noms de fichiers à traiter
        $filenames = [self::FILE_ARTWEB, self::FILE_PRIX, self::FILE_STOCK];

        // Initialisation du compteur de lignes de tâche
        $jobLine = 0;

        // Boucle sur les noms de fichiers
        foreach ($filenames as $filename) {
            // Vérification du temps d'arrêt de la tâche cron
            if (($stopTime < time()) && ($jobLine > $nbCron)) {
                return $jobLine;
            }

            $jobLine++;

            // Ignorer les lignes de tâche avant le numéro spécifié
            if ($jobLine < $nbCron) {
                continue;
            }

            // Suppression de la table associée au fichier
            try {
                Outils::query('DROP TABLE pimcore.eci_midle_file_' . $filename . ';');
            } catch (Exception $e) {
                Outils::addLog(message: 'Error dans le truncate de la table :' . $e->getMessage());
            }

            // Traitement du fichier CSV
            try {
                DbFile::buildFromCsv(path: __DIR__ . '/../../files/' . $filename . '.TXT');
            } catch (Exception $e) {
                Outils::addLog(message: 'Error dans le stockage du CSV :' . $e->getMessage());
            }
        }

        // Tâche terminée
        return true;
    }

    /**
     * Importe tout le catalogue dans Pimcore
     * @param array $params Paramètres du cronjob
     * @return bool|int|Response
     */
    #[Route('/cron/import_catalog')]
    function cronImportCatalog($params = ['cron' => 0, 'nbCron' => 0, 'stopTime' => 0]): bool|int|Response
    {
        ['cron' => $cron, 'nbCron' => $nbCron, 'stopTime' => $stopTime] = $params;
        $diffusion = Diffusion::getByPath(path: self::DIFFUSION_PATH);
        $langPS = 1;

        $id_genre = $this->getOrCreateAttribute(name: 'Genre', diffusion: $diffusion);
        $id_couleur = $this->getOrCreateAttribute(name: 'Couleur', diffusion: $diffusion);

        $list_code_modele = Outils::query(req: 'SELECT DISTINCT CODE_MODELE FROM pimcore.eci_midle_file_' . self::FILE_ARTWEB . ';');
        $id_category_default = DataObject::getByPath(path: self::DEFAULT_CATEGORY_PATH);
        $jobLine = 0;

        foreach ($list_code_modele as $data_code_modele) {
            // Vérification du temps d'arrêt de la tâche cron
            if (($stopTime < time()) && ($jobLine > $nbCron)) {
                return $jobLine;
            }

            $jobLine++;

            // Ignorer les lignes de tâche avant le numéro spécifié
            if ($jobLine < $nbCron) {
                continue;
            }

            $code_modele = $data_code_modele['CODE_MODELE'];
            $array_data = $this->fetchModelData(code_modele: $code_modele);
            $product_data = $array_data[0];
            $marque = $this->createMarque(product_data: $product_data, diffusion: $diffusion, langPS: $langPS);
            $prod = $this->createProductData(product_data: $product_data, id_category_default: $id_category_default);

            $declinations = $this->createDeclinations(
                array_data: $array_data,
                diffusion: $diffusion,
                id_genre: $id_genre,
                id_couleur: $id_couleur,
            );

            if (!$id_prod = Outils::getExist(search: $product_data['CODE_MODELE'], source: $diffusion->getID())) {
                $id_prod = Outils::putCreateProduct(
                    prod: $prod,
                    diffusion: $diffusion,
                    categList: [],
                    caracList: [],
                    marqueList: $marque ? Marque::getById($marque) : null,
                    imageList: [],
                    decliList: $declinations,
                    langPS: $langPS
                );
            }
            return new Response("ok");
        }

        return new Response("ok");
    }

    /**
     * Récupère une caractéristique, sinon la crée
     *
     * @param string $caracName Nom de la caractéristique
     * @param Diffusion $diffusion Diffusion à utiliser
     * @return int Id de la caractéristique
     */
    private function getOrCreateCarac(string $caracName, Diffusion $diffusion): int
    {
        $id_carac = Outils::getExist(search: $caracName, source: $diffusion->getID(), champ: 'crossid', objet: 'carac');

        if ($id_carac == 0) {
            $id_carac = Outils::putCreateCarac(
                carac: json_decode(json_encode(['name' => $caracName, 'active' => 1])),
                diffusion: $diffusion,
                langPS: 1
            );
        }

        return $id_carac;
    }

    /**
     * Récupère un attribut s'il existe, sinon le crée
     * @param string $name Nom de l'attribut
     * @param Diffusion $diffusion Diffusion à utiliser
     * @return int ID de l'attribut
     */
    private function getOrCreateAttribute(string $name, Diffusion $diffusion): int
    {
        $id_carac = Outils::getExist($name, $diffusion->getID(), 'crossid', 'carac');
        if ($id_carac == 0) {
            $id_carac = Outils::putCreateAttribute(
                attr: $this->arrayToStd(['name' => $name, 'active' => 1]),
                diffusion: $diffusion,
                langPS: 1
            );
        }

        return $id_carac;
    }

    /**
     * Récupère toutes les informations d'un produit Ginkoia d'après son code modèle
     * @param string $code_modele Code modele ginkoia à utiliser
     * @return array Donnée du produit
     */
    private function fetchModelData(string $code_modele): array
    {
        try {
            return Outils::query('
                SELECT * 
                FROM pimcore.eci_midle_file_' . self::FILE_ARTWEB . ' AS artweb
                LEFT JOIN pimcore.eci_midle_file_' . self::FILE_STOCK . ' AS stock ON artweb.CODE_ARTICLE = stock.CODE_ARTICLE
                LEFT JOIN pimcore.eci_midle_file_' . self::FILE_PRIX . ' AS prix ON artweb.CODE_ARTICLE = prix.CODE_ARTICLE
                WHERE artweb.CODE_MODELE = ' . $code_modele . ' 
                LIMIT 5;
        ');
        } catch (Exception $e) {
            Outils::addLog(message: 'result sql error : ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Créée une déclinaison
     * @param array $array_data
     * @param Diffusion $diffusion
     * @param int $id_genre
     * @param int $id_couleur
     * @return array
     */
    private function createDeclinations(array $array_data, Diffusion $diffusion, int $id_genre, int $id_couleur): array
    {
        $decli_list = [];

        foreach ($array_data as $declinaison_data) {
            if (!Outils::getExist($declinaison_data['GENRE'], $diffusion->getID(), 'crossid', 'caracValue')) {
                Outils::putCreateAttributeValue(
                    attr: $this->arrayToStd(['name' => $declinaison_data['GENRE'], 'active' => 1, 'id' => $declinaison_data['GENRE']]),
                    diffusion: $diffusion,
                    id_parent: $id_genre,
                    langPS: 1
                );
            }

            if (!Outils::getExist($declinaison_data['COULEUR'], $diffusion->getID(), 'crossid', 'caracValue')) {
                Outils::putCreateAttributeValue(
                    attr: $this->arrayToStd(['name' => $declinaison_data['COULEUR'], 'active' => 1, 'id' => $declinaison_data['GENRE']]),
                    diffusion: $diffusion,
                    id_parent: $id_couleur,
                    langPS: 1
                );
            }
            $declinaison =  $this->arrayToStd([
                'reference' => $declinaison_data['CODE_ARTICLE'],
                'supplier_reference' => $declinaison_data['CODE_FOURN'],
                'ean13' => array_key_exists('CODE_EAN', $declinaison_data) ? $this->validateEAN13($declinaison_data['CODE_EAN']) : null,
                'upc' => (float)$declinaison_data['PXVTE'],
                'mpn' => (float)$declinaison_data['PXVTE_N'],
                'wholesale_price' => (float)$declinaison_data['PXVTE'],
                'price' => (float)$declinaison_data['PUMP'],
                'quantity' => $declinaison_data['QTE_PREVENTE'],
                'weight' => $declinaison_data['POIDS'],
            ]);
            $decli_id = Outils::putCreateDeclinaison(
                decli: $declinaison,
                diffusion: $diffusion,
                lists: [],
//                tabAssoc: [],
                tabAssoc: [ Attribut::getById(id: $id_couleur)],
//                tabAssoc: [Attribut::getById(id: $id_genre), Attribut::getById(id: $id_couleur)],
                langPS: 1
            );
            dump([$id_genre => Attribut::getById(id: $id_genre), $id_couleur => Attribut::getById(id: $id_couleur)]);
//            Outils::putCreatePriceSell(
//                id_declinaison: $decli_id,
//                id_diffusion: $diffusion->getId(),
//                price: (float)$declinaison_data['PXVTE']
//            );
//            dump([$decli_id => Declinaison::getById(id: $decli_id), "data" => $declinaison]);
            $decli_list[] = Declinaison::getById(id: $decli_id);
        }
        dump($decli_list);
        return array_filter($decli_list);
    }

    /**
     * Crée une marque dans Pimcore
     * @param array $product_data Données brutes de Ginkoia
     * @param Diffusion $diffusion Diffusion à utiliser
     * @param int $langPS ID de la langue à utiliser
     * @return false|int|mixed|string|null
     */
    private function createMarque(array $product_data, Diffusion $diffusion, int $langPS): mixed
    {
        $marque = json_decode(
            json: json_encode([
                'id' => $product_data['IDMARQUE'],
                'name' => $product_data['MARQUE'],
                'active' => 1,
            ]),
            associative: false
        );

        $idMarq = Outils::getExist($marque->id, $diffusion->getID(), 'crossid', 'marque');

        if ($idMarq == 0) {
            $idMarq = Outils::putCreateMarque(marque: $marque, diffusion: $diffusion, langPS: $langPS);
        }

        return $idMarq;
    }

    /**
     * Crée les données au bon format d'un produit, sous forme d'objet
     * @param array $product_data
     * @param int $id_category_default
     * @return mixed
     */
    private function createProductData(array $product_data, int|null $id_category_default): mixed
    {
        return $this->arrayToStd([
            "tax_name" => null,
            "tax_rate" => null,
            "id_supplier" => null,
            "id_category_default" => $id_category_default,
            "id_shop_default" => null,
            "manufacturer_name" => $product_data['MARQUE'],
            "supplier_name" => null,
            "name" => [
                "1" => $product_data['PRODUIT'],
            ],
            "description" => [
                "1" => "",
            ],
            "description_short" => [
                "1" => "",
            ],
            "quantity" => $product_data['QTE_PREVENTE'],
            "minimal_quantity" => "1",
            "low_stock_threshold" => null,
            "low_stock_alert" => "0",
            "available_now" => [
                "1" => "",
                "2" => "",
            ],
            "available_later" => [
                "1" => "",
                "2" => "",
            ],
            "price" => (float)$product_data['PXVTE'],
            "specificPrice" => 0,
            "additional_shipping_cost" => "0.000000",
            "wholesale_price" => "0.000000",
            "on_sale" => "0",
            "online_only" => "0",
            "unity" => "",
            "unit_price" => 0,
            "unit_price_ratio" => "0.000000",
            "ecotax" => "0.000000",
            "reference" => $product_data['CODE_MODELE'],
            "supplier_reference" => $product_data['CODE_FOURN'],
            "location" => "",
            "width" => "0.000000",
            "height" => "0.000000",
            "depth" => "0.000000",
            "weight" => (float)$product_data['POIDS'],
            "ean13" => key_exists('CODE_EAN', $product_data) ? $this->validateEAN13($product_data['CODE_EAN']) : null,
            "isbn" => "10",
            "upc" => "",
            "mpn" => "",
            "meta_description" => [
                "1" => "",
                "2" => "",
            ],
            "meta_keywords" => [
                "1" => "",
                "2" => "",
            ],
            "meta_title" => [
                "1" => "",
                "2" => "",
            ],
            "quantity_discount" => "0",
            "customizable" => "0",
            "new" => null,
            "uploadable_files" => "0",
            "text_fields" => "0",
            "active" => "1",
            "redirect_type" => "301-category",
            "id_type_redirected" => "0",
            "available_for_order" => "1",
            "available_date" => "0000-00-00",
            "show_condition" => "0",
            "condition" => "new",
            "show_price" => "1",
            "indexed" => "1",
            "visibility" => "both",
            "tags" => null,
            "state" => "1",
            "base_price" => (float)$product_data['PXVTE'],
            "id_tax" => null,
            "id_color_default" => 0,
            "advanced_stock_management" => "0",
            "out_of_stock" => "2",
            "depends_on_stock" => null,
            "isFullyLoaded" => false,
            "cache_is_pack" => "0",
            "cache_has_attachments" => "0",
            "is_virtual" => "0",
            "id_pack_product_attribute" => null,
            "cache_default_attribute" => "1",
            "category" => false,
            "pack_stock_type" => "3",
            "additional_delivery_times" => "1",
            "delivery_in_stock" => [
                "1" => "",
                "2" => "",
            ],
            "delivery_out_stock" => [
                "1" => "",
                "2" => "",
            ],
            "product_type" => "combinations",
            "id" => $product_data['CODE_MODELE'],
            "id_shop_list" => [],
            "force_id" => false,
        ]);
    }

    /**
     * Transforme un tableau en objet
     * @param array $array Tableau PHP à renvoyer en objet
     * @return mixed
     */
    private function arrayToStd(array $array): mixed
    {
        return json_decode(json_encode($array), false);
    }

    #[Route('/cron/import_catalog')]
    function cronImportCatalogOld($params = ['cron' => 0, 'nbCron' => 0, 'stopTime' => 0]): bool|int|Response
    {
//        return true;
        ['cron' => $cron, 'nbCron' => $nbCron, 'stopTime' => $stopTime] = $params;
        $diffusion = Diffusion::getByPath(self::DIFFUSION_PATH);
        $langPS = 1;

        $list_code_modele = Outils::query('SELECT DISTINCT CODE_MODELE FROM pimcore.eci_midle_file_' . self::FILE_ARTWEB . ';');
        $id_category_default = Diffusion::getByPath(self::DEFAULT_CATEGORY_PATH);
        $jobLine = 0;

        $id_genre = Outils::getExist('Genre', $diffusion->getID(), 'crossid', 'carac');

        if ($id_genre == 0) {
            $id_genre = Outils::putCreateCarac(carac: json_decode(json_encode([
                'name' => 'Genre', 'active' => 1, 'id' => 'genre'
            ])), diffusion: $diffusion, langPS: $langPS);
        }

        $id_couleur = Outils::getExist('Couleur', $diffusion->getID(), 'crossid', 'carac');

        if ($id_couleur == 0) {
            $id_couleur = Outils::putCreateCarac(carac: json_decode(json_encode([
                'name' => 'Couleur', 'active' => 1
            ])), diffusion: $diffusion, langPS: $langPS);
        }
        dump($list_code_modele);
        foreach ($list_code_modele as $data_code_modele) {
            $code_modele = $data_code_modele['CODE_MODELE'];

            try {
                $array_data = Outils::query('
                select 
                    artweb.CODE_ARTICLE AS CODE_ARTICLE,
                    artweb.CODE_MODELE AS CODE_MODELE,
                    artweb.CODE_NK AS CODE_NK,
                    artweb.IDMARQUE AS IDMARQUE,
                    artweb.MARQUE AS MARQUE,
                    artweb.CODE_FOURN AS CODE_FOURN,
                    artweb.PRODUIT AS PRODUIT,
                    artweb.COULEUR AS COULEUR,
                    artweb.GCS_ID AS GCS_ID,
                    artweb.TAILLE AS TAILLE,
                    artweb.TGF_ID AS TGF_ID,
                    artweb.TVA AS TVA,
                    artweb.GENRE AS GENRE,
                    artweb.CLASSEMENT1 AS CLASSEMENT1,
                    artweb.CLASSEMENT2 AS CLASSEMENT2,
                    artweb.CLASSEMENT3 AS CLASSEMENT3,
                    artweb.CLASSEMENT4 AS CLASSEMENT4,
                    artweb.CLASSEMENT5 AS CLASSEMENT5,
                    artweb.COLLECTION AS COLLECTION,
                    artweb.WEB_DETAIL AS WEB_DETAIL,
                    artweb.WEB_COMPOSITION AS WEB_COMPOSITION,
                    artweb.POIDS AS POIDS,
                    artweb.POIDSL AS POIDSL,
                    artweb.CODE_CHRONO AS CODE_CHRONO,
                    artweb.ARCHIVER AS ARCHIVER,
                    artweb.CODE_COULEUR AS CODE_COULEUR,
                    artweb.WEB AS WEB,
                    artweb.PREVENTE AS PREVENTE,
                    artweb.QTE_PREVENTE AS QTE_PREVENTE,
                    artweb.DELAIS_PREVENTE AS DELAIS_PREVENTE,
                    artweb.ETAT_DATA AS ETAT_DATA,
                    artweb.CODE_EAN AS CODE_EAN,
                    prix.PXVTE AS PXVTE,
                    prix.PXVTE_N AS PXVTE_N,
                    prix.PXDESTOCK AS PXDESTOCK,
                    prix.PUMP AS PUMP,
                    prix.ETAT_DATA AS ETAT_DATA_PRIX,
                    prix.CODE_EAN AS CODE_EAN_PRIX,
                    stock.ID_SEUIL AS ID_SEUIL_STOCK,
                    stock.LIB_SEUIL AS LIB_SEUIL_STOCK,
                    stock.QTE_STOCK AS QTE_STOCK_STOCK,
                    stock.MAG_ID AS MAG_ID_STOCK,
                    stock.QTE_SEUIL AS QTE_SEUIL_STOCK,
                    stock.ETAT_DATA AS ETAT_DATA_STOCK,
                    stock.CODE_EAN AS CODE_EAN_STOCK, 
                    stock.QTE_PREVENTE AS QTE_PREVENTE_STOCK
                from pimcore.eci_midle_file_' . self::FILE_ARTWEB . ' AS artweb
                    LEFT JOIN pimcore.eci_midle_file_' . self::FILE_STOCK . ' AS stock ON artweb.CODE_ARTICLE = stock.CODE_ARTICLE
                    LEFT JOIN pimcore.eci_midle_file_' . self::FILE_PRIX . ' AS prix ON artweb.CODE_ARTICLE = prix.CODE_ARTICLE
                where artweb.CODE_MODELE = ' . $code_modele . ' 
                limit 5;
            ');
            } catch (Exception $e) {
                Outils::addLog(message: 'result sql error : ' . $e->getMessage());
            }
            $product_data = $array_data[0];
            $marque = json_decode(
                json: json_encode([
                    'id' => $product_data['IDMARQUE'],
                    'name' => $product_data['MARQUE'],
                    'active' => 1,
                ]),
                associative: false
            );

            $prod = $this->arrayToStd([
                "tax_name" => null,
                "tax_rate" => null,
//                    "id_manufacturer" => $data['IDMARQUE'],
                "id_supplier" => null,
                "id_category_default" => $id_category_default,
                "id_shop_default" => null,
                "manufacturer_name" => $product_data['MARQUE'],
                "supplier_name" => null,
                "name" => [
                    "1" => $product_data['PRODUIT'],
                ],
                "description" => [
                    "1" => "",
//                        "2" => "Symbole de légèreté et de délicatesse, le colibri évoque la gaieté et la curiosité. La collection PolyFaune de la marque Studio Design propose des pièces aux coupes basiques et aux visuels colorés inspirés des origamis japonais traditionnels. À porter avec un chino ou un jean. Le procédé d'impression par sublimation garantit la qualité et la longévité des couleurs.",
                ],
                "description_short" => [
                    "1" => "",
//                        "2" => "Coupe classique, col rond, manches courtes. T-shirt en coton pima extra-fin à fibres longues.",
                ],
                "quantity" => $product_data['QTE_PREVENTE'],
                "minimal_quantity" => "1",
                "low_stock_threshold" => null,
                "low_stock_alert" => "0",
                "available_now" => [
                    "1" => "",
                    "2" => "",
                ],
                "available_later" => [
                    "1" => "",
                    "2" => "",
                ],
                "price" => (float)$product_data['PXVTE'],
                "specificPrice" => 0,
                "additional_shipping_cost" => "0.000000",
                "wholesale_price" => "0.000000",
                "on_sale" => "0",
                "online_only" => "0",
                "unity" => "",
                "unit_price" => 0,
                "unit_price_ratio" => "0.000000",
                "ecotax" => "0.000000",
                "reference" => $product_data['CODE_MODELE'],
                "supplier_reference" => $product_data['CODE_FOURN'],
                "location" => "",
                "width" => "0.000000",
                "height" => "0.000000",
                "depth" => "0.000000",
                "weight" => (float)$product_data['POIDS'],
                "ean13" => key_exists('CODE_EAN', $product_data) ? $this->validateEAN13($product_data['CODE_EAN']) : null,
//                    "ean13" => $data['CODE_EAN'],
                "isbn" => "10",
                "upc" => "",
                "mpn" => "",
//                "link_rewrite" => [
//                        "1" => "hummingbird-printed-t-shirt",
//                        "2" => "hummingbird-printed-t-shirt",
//                ],
                "meta_description" => [
                    "1" => "",
                    "2" => "",
                ],
                "meta_keywords" => [
                    "1" => "",
                    "2" => "",
                ],
                "meta_title" => [
                    "1" => "",
                    "2" => "",
                ],
                "quantity_discount" => "0",
                "customizable" => "0",
                "new" => null,
                "uploadable_files" => "0",
                "text_fields" => "0",
                "active" => "1",
                "redirect_type" => "301-category",
                "id_type_redirected" => "0",
                "available_for_order" => "1",
                "available_date" => "0000-00-00",
                "show_condition" => "0",
                "condition" => "new",
                "show_price" => "1",
                "indexed" => "1",
                "visibility" => "both",
//                    "date_add" => "2021-11-29 17:57:09",
//                    "date_upd" => "2022-11-29 21:47:24",
                "tags" => null,
                "state" => "1",
                "base_price" => (float)$product_data['PXVTE'],
//                "id_tax_rules_group" => "1",
                "id_tax" => null,
                "id_color_default" => 0,
                "advanced_stock_management" => "0",
                "out_of_stock" => "2",
                "depends_on_stock" => null,
                "isFullyLoaded" => false,
                "cache_is_pack" => "0",
                "cache_has_attachments" => "0",
                "is_virtual" => "0",
                "id_pack_product_attribute" => null,
                "cache_default_attribute" => "1",
                "category" => false,
                "pack_stock_type" => "3",
                "additional_delivery_times" => "1",
                "delivery_in_stock" => [
                    "1" => "",
                    "2" => "",
                ],
                "delivery_out_stock" => [
                    "1" => "",
                    "2" => "",
                ],
                "product_type" => "combinations",
                "id" => $product_data['CODE_MODELE'],
                "id_shop_list" => [],
                "force_id" => false,
            ]);

            if ($marque) {
//            $marqueList = Outils::putCreateMarque(marque: $marque, diffusion: $diffusion, langPS: $langPS);
//            $marqueList = $this->pullMarque($marque, $diffusion, $langPS);
                $idMarq = Outils::getExist($marque->id, $diffusion->getID(), 'crossid', 'marque');
                if ($idMarq == 0) {
//            $idMarq = $this->createMarque($marque, $diffusion, $langPS);
                    $idMarq = Outils::putCreateMarque(marque: $marque, diffusion: $diffusion, langPS: $langPS);
                }
                $marqueList = Marque::getById(id: $idMarq);
            } else {
                $marqueList = 0;
            }

            // Déclinaisons
            $decli_list = [];
            $carac_list = [];
            foreach ($array_data as $declinaison_data) {
                if (($stopTime < time()) && ($jobLine > $nbCron)) {
                    return $jobLine;
                }
                $jobLine++;
                if ($jobLine < $nbCron) {
                    continue;
                }
                if (!Outils::getExist($declinaison_data['GENRE'], $diffusion->getID(), 'crossid', 'caracValue')) {
                    Outils::putCreateCaracValue(
                        carac: json_decode(json_encode(['value' => $declinaison_data['GENRE'], 'active' => 1, 'id' => $declinaison_data['GENRE']])),
                        diffusion: $diffusion,
                        id_parent: $id_genre,
                        langPS: $langPS
                    );
                }

                if (!Outils::getExist($declinaison_data['COULEUR'], $diffusion->getID(), 'crossid', 'caracValue')) {
                    Outils::putCreateCaracValue(
                        carac: json_decode(json_encode(['value' => $declinaison_data['COULEUR'], 'active' => 1, 'id' => $declinaison_data['GENRE']])),
                        diffusion: $diffusion,
                        id_parent: $id_couleur,
                        langPS: $langPS
                    );
                }

                $carac_list[] = Carac::getById(id: $id_genre);
                $carac_list[] = Carac::getById(id: $id_couleur);
                $decli_id = Outils::putCreateDeclinaison(decli: json_decode(json_encode([
//                    'id_product' => $declinaison_data['CODE_MODELE'],
                    'reference' => $declinaison_data['CODE_ARTICLE'],
                    'supplier_reference' => $declinaison_data['CODE_FOURN'],
                    'location' => null,
                    'ean13' => array_key_exists('CODE_EAN', $declinaison_data) ? $this->validateEAN13($declinaison_data['CODE_EAN']) : null,
                    'isbn' => null,
                    'upc' => (float)$declinaison_data['PXVTE'],
                    'mpn' => (float)$declinaison_data['PXVTE_N'],
                    'wholesale_price' => (float)$declinaison_data['PXVTE'],
                    'price' => (float)$declinaison_data['PUMP'],
                    'unit_price_impact' => null,
                    'ecotax' => null,
                    'minimal_quantity' => null,
                    'low_stock_threshold' => null,
                    'low_stock_alert' => null,
                    'quantity' => $declinaison_data['QTE_PREVENTE'],
                    'weight' => $declinaison_data['POIDS'],
                    'default_on' => null,
                    'available_date' => null,
//                    'id' => $declinaison_data['CODE_ARTICLE'],
                    'id_shop_list' => null,
                    'force_id' => null,
                ]), false), diffusion: $diffusion, lists: [], tabAssoc: [], langPS: $langPS);

                $decli_list[] = Declinaison::getById(id: $decli_id);
//                break;
            }


//            dump([$prod,$diffusion,'caraclist' => array_unique($carac_list),$marqueList,$decli_list,$langPS]);

            if (!$id_prod = Outils::getExist($product_data['CODE_MODELE'], $diffusion->getID(), 'crossid', 'product')) {
                $id_prod = Outils::putCreateProduct(
                    prod: $prod,
                    diffusion: $diffusion,
                    categList: [],
                    caracList: array_unique($carac_list),
                    marqueList: $marqueList,
                    imageList: [],
                    decliList: $decli_list,
                    langPS: $langPS
                );
            }

            if (Outils::getExist($product_data['PXVTE'], $diffusion->getID(), 'crossid', 'priceSelling')) {
                continue;
            }
            Outils::putCreatePriceSell(id_prod: $id_prod, id_declinaison: $decli_id, id_diffusion: $diffusion->getId(), price: (float)$product_data['PXVTE']);
        }
        return true;
//        return new Response("OK");
    }

    /**
     * Met à jour le stock des produits dans le cadre d'une tâche cron.
     *
     * @param array $params Paramètres de la tâche cron.
     *                      - 'cron' : Indicateur de tâche cron.
     *                      - 'nbCron' : Nombre de lignes de tâche cron à traiter.
     *                      - 'stopTime' : Temps d'arrêt de la tâche cron.
     *
     * @return int|bool Retourne le nombre de lignes de tâche cron traitées ou TRUE si la tâche est terminée.
     *
     * @throws Exception
     */
    #[Route('/cron/update_stock')]
    function cronUpdateStock($params = ['cron' => 0, 'nbCron' => 0, 'stopTime' => 0])
    {
        // Extraction des paramètres
        ['cron' => $cron, 'nbCron' => $nbCron, 'stopTime' => $stopTime] = $params;

        // Récupération de l'objet Diffusion
        $diffusion = Diffusion::getByPath(self::DIFFUSION_PATH);

        // Langue par défaut
        $langPS = 1;

        // Récupération de la liste des codes d'article
        $list_code_article = Outils::query('SELECT DISTINCT CODE_ARTICLE FROM pimcore.eci_midle_file_' . self::FILE_STOCK . ';');

        // Récupération de l'ID de la catégorie par défaut
        $id_category_default = DataObject::getByPath(self::DEFAULT_CATEGORY_PATH);

        // Initialisation du compteur de lignes de tâche
        $jobLine = 0;

        // Boucle sur la liste des codes d'article
        foreach ($list_code_article as $data_code_article) {
            $code_article = $data_code_article['CODE_ARTICLE'];

            // Vérification du temps d'arrêt de la tâche cron
            if (($stopTime < time()) && ($jobLine > $nbCron)) {
                return $jobLine;
            }

            $jobLine++;

            // Ignorer les lignes de tâche avant le numéro spécifié
            if ($jobLine < $nbCron) {
                continue;
            }

            try {
                // Récupération des données depuis la base de données
                $array_data = Outils::query('
                SELECT
                     artweb.CODE_ARTICLE AS CODE_ARTICLE,
                    artweb.CODE_MODELE AS CODE_MODELE,
                    artweb.CODE_NK AS CODE_NK,
                    artweb.IDMARQUE AS IDMARQUE,
                    artweb.MARQUE AS MARQUE,
                    artweb.CODE_FOURN AS CODE_FOURN,
                    artweb.PRODUIT AS PRODUIT,
                    artweb.COULEUR AS COULEUR,
                    artweb.GCS_ID AS GCS_ID,
                    artweb.TAILLE AS TAILLE,
                    artweb.TGF_ID AS TGF_ID,
                    artweb.TVA AS TVA,
                    artweb.GENRE AS GENRE,
                    artweb.CLASSEMENT1 AS CLASSEMENT1,
                    artweb.CLASSEMENT2 AS CLASSEMENT2,
                    artweb.CLASSEMENT3 AS CLASSEMENT3,
                    artweb.CLASSEMENT4 AS CLASSEMENT4,
                    artweb.CLASSEMENT5 AS CLASSEMENT5,
                    artweb.COLLECTION AS COLLECTION,
                    artweb.WEB_DETAIL AS WEB_DETAIL,
                    artweb.WEB_COMPOSITION AS WEB_COMPOSITION,
                    artweb.POIDS AS POIDS,
                    artweb.POIDSL AS POIDSL,
                    artweb.CODE_CHRONO AS CODE_CHRONO,
                    artweb.ARCHIVER AS ARCHIVER,
                    artweb.CODE_COULEUR AS CODE_COULEUR,
                    artweb.WEB AS WEB,
                    artweb.PREVENTE AS PREVENTE,
                    artweb.QTE_PREVENTE AS QTE_PREVENTE,
                    artweb.DELAIS_PREVENTE AS DELAIS_PREVENTE,
                    artweb.ETAT_DATA AS ETAT_DATA,
                    artweb.CODE_EAN AS CODE_EAN,
                    prix.PXVTE AS PXVTE,
                    prix.PXVTE_N AS PXVTE_N,
                    prix.PXDESTOCK AS PXDESTOCK,
                    prix.PUMP AS PUMP,
                    prix.ETAT_DATA AS ETAT_DATA_PRIX,
                    prix.CODE_EAN AS CODE_EAN_PRIX,
                    stock.ID_SEUIL AS ID_SEUIL_STOCK,
                    stock.LIB_SEUIL AS LIB_SEUIL_STOCK,
                    stock.QTE_STOCK AS QTE_STOCK_STOCK,
                    stock.MAG_ID AS MAG_ID_STOCK,
                    stock.QTE_SEUIL AS QTE_SEUIL_STOCK,
                    stock.ETAT_DATA AS ETAT_DATA_STOCK,
                    stock.CODE_EAN AS CODE_EAN_STOCK, 
                    stock.QTE_PREVENTE AS QTE_PREVENTE_STOCK
                FROM pimcore.eci_midle_file_' . self::FILE_STOCK . ' AS stock
                    LEFT JOIN pimcore.eci_midle_file_' . self::FILE_PRIX . ' AS prix ON prix.CODE_ARTICLE = stock.CODE_ARTICLE
                    LEFT JOIN pimcore.eci_midle_file_' . self::FILE_ARTWEB . ' AS artweb ON artweb.CODE_ARTICLE = stock.CODE_ARTICLE
                WHERE stock.CODE_ARTICLE = \'' . $code_article . '\'
                LIMIT 1; 
            ');
            } catch (Exception $e) {
                // Log d'une erreur SQL
                Outils::addLog(message: 'result sql error : ' . $e->getMessage());
                continue;
            }

            // Récupération des données de stock
            $stock = $array_data[0];

            // Vérification de l'existence du produit et de la déclinaison
            if (!$product_id = Outils::getExist($stock['CODE_MODELE'], $diffusion->getID(), 'crossid', 'product')) {
                continue;
            }

            if (!$declinaison_id = Outils::getExist($stock['CODE_ARTICLE'], $diffusion->getID(), 'crossid', 'declinaison')) {
                continue;
            }

            // Vérification de l'existence de l'entrepôt
            if (!$id_entrepot = Entrepot::getByPath('/Entrepot/GINKOIA_' . $stock['MAG_ID'])) {
                // Création de l'entrepôt s'il n'existe pas
                $entrepot = new Entrepot();
                $entrepot->setKey('GINKOIA_' . $stock['MAG_ID']);
                $entrepot->setName($stock['MAG_ID']);
                $entrepot->setParentId(WebsiteSetting::getByName('folderEntrepot')->getData());
                $entrepot->setPublished(true);
                $entrepot->save();
                $id_entrepot = $entrepot->getId();
            }

            // Ajout du mouvement de stock
            $id_move = Outils::addMouvementStock(
                product: $product_id,
                declinaison: $declinaison_id,
                qty: $stock['QTE_STOCK'],
                entrepot: $id_entrepot,
                diffusion: $diffusion->getId()
            );

            // Mise à jour du prix de vente
            Outils::putCreatePriceSell(id_prod: $product_id, id_declinaison: $declinaison_id, id_diffusion: $diffusion->getId(), price: (float)$stock['PXVTE']);
        }

        // Tâche terminée
        return true;
        // return new Response("OK");
    }

    /**
     * Actions à effectuer lors de la création d'une commande dans Pimcore
     *
     * @param array $params Les paramètres de la commande
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route('/hook/create-order', name: 'create_product')]
    public function hookCreateOrder($params): Response
    {
        return true;

        // Ajout d'un log pour indiquer la création d'une commande
        Outils::addLog('Création d\'une commande', 1, [], 'NOMDULOG');

        // Récupération de l'objet Order à partir des paramètres
        $order = $params['order'];

        // Création d'un nouvel élément XML
        $xml = new SimpleXMLElement('<?xml version="1.0"?><Commande></Commande>');

        // Ajout des éléments au XML
        $xml->addChild('CommandeNum', $order->getId_order());
        $xml->addChild('CommandeId', $order->getId_order());

        // Ajout de la date de commande au format spécifié
        if ($date_add = $order->getDate_add()) {
            $xml->addChild('CommandeDate', $date_add->format('Y-m-d H:i:s'));
            $xml->addChild('DateReglement', $date_add->format('Y-m-d H:i:s'));
        }

        // Ajout du statut de la commande
        if ($current_state = $order->getCurrent_state()) {
            $xml->addChild('Statut', $current_state->getName());
        }

        // Mode de paiement et date de paiement
        $reglementsXML = $xml->addChild('Reglements');
        $reglementXML = $reglementsXML->addChild('Reglement');

        // Gestion des informations de paiement
        /** @var Paiement $payment */
        if ($payment = $order->getPayment()) {
            $reglementXML->addChild('Mode', $payment->getName()); // Supposant que 'getName()' retourne le mode de paiement
            $reglementXML->addChild('MontantTTC', /* Valeur du montant TTC du paiement */);
            $reglementXML->addChild('Date', /* Date du paiement au format 'Y-m-d H:i:s' */);
        }

        // Ajout d'informations sur le client
        /** @var Client $client */
        $client = $order->getCustomer();
        $clientXML = $xml->addChild('Client');

        // Ajout des informations du client
        $clientXML->addChild('CodeClient', $client->getId_customer());
        $clientXML->addChild('Email', $client->getEmail());

        // Gérer l'adresse de livraison
        $addressLivrXML = $clientXML->addChild('AddressLivr');
        if ($livraison = $order->getAddress_delivery()) {
            $this->addAddressElements($addressLivrXML, $livraison);
        }

        // Gérer l'adresse de facturation
        $addressFactXML = $clientXML->addChild('AddressFact');
        if ($facturation = $order->getAddress_invoice()) {
            $this->addAddressElements($addressFactXML, $facturation);
        }

        // Gestion des adresses du client (en utilisant la classe Address)
        /** @var Address $addresses */
        $addresses = $client->getAddress();
        foreach ($addresses as $address) {
            $addressXML = $clientXML->addChild('Adresse');
            $addressXML->addChild('Type', $address->getTypeadd()); // Type d'adresse
            $addressXML->addChild('Societe', $address->getCompany());
            $addressXML->addChild('Nom', $address->getLastname());
            $addressXML->addChild('Prenom', $address->getFirstname());
            $addressXML->addChild('Adresse1', $address->getAddress1());
            $addressXML->addChild('Adresse2', $address->getAddress2());
            $addressXML->addChild('Adresse3', $address->getAddress3());
            $addressXML->addChild('CodePostal', $address->getPostcode());
            $addressXML->addChild('Ville', $address->getCity());
            $addressXML->addChild('Pays', $address->getCountry());
            $addressXML->addChild('Telephone', $address->getPhone());
            $addressXML->addChild('Telephone2', $address->getPhone2());
            $addressXML->addChild('Email', $address->getEmail());
        }

        // Gérer les lignes de commande (produits)
        $linesXML = $xml->addChild('Lignes');
        foreach ($order->getOrderdetail() as $detail) {
            $lineXML = $linesXML->addChild('Ligne');

            // Ajouter les détails de chaque produit
            $lineXML->addChild('TypeLigne', 'Ligne');
            $lineXML->addChild('Code', $detail->getReference()); // Ou autre champ si 'reference' n'est pas approprié
            $lineXML->addChild('CodeEAN', $detail->getEan13());
            $lineXML->addChild('Designation', htmlspecialchars($detail->getName())); // Utilisation de CDATA pour les caractères spéciaux
            $lineXML->addChild('PUBrutHT', $detail->getPrice());
            $lineXML->addChild('PUBrutTTC', $detail->getUnit_tax_incl()); // Si ce champ représente le prix unitaire TTC
            $lineXML->addChild('Qte', $detail->getQuantity());
            $lineXML->addChild('PUHT', $detail->getPrice()); // Si ce champ représente le prix unitaire HT
            $lineXML->addChild('PUTTC', $detail->getUnit_tax_incl()); // Si ce champ représente le prix unitaire TTC
            $lineXML->addChild('TxTva', $detail->getTax_rate()); // Taux de TVA
            $lineXML->addChild('PXHT', $detail->getTotal_tax_excl()); // Total prix HT
            $lineXML->addChild('PXTTC', $detail->getTotal_tax_incl()); // Total prix TTC
        }


        // Total, TVA, etc.
        $xml->addChild('SousTotalHT', $order->getTotal_products()); // Sous-total hors taxes
        $xml->addChild('TotalHT', $order->getTotal_paid_tax_excl()); // Total hors taxes
        $xml->addChild('MontantTVA', $order->getTotal_discounts_tax_incl()); // Montant de la TVA
        $xml->addChild('TotalTTC', $order->getTotal_paid_tax_incl()); // Total TTC
        $xml->addChild('Netpayer', $order->getTotal_paid_real()); // Montant net à payer

        // Frais de port
        $xml->addChild('FraisPort', $order->getTotal_shipping()); // Frais de port

        // TVA par taux
        $tvaxml = $xml->addChild('TVAS');
        $tva = $tvaxml->addChild('TVA');
        $tva->addChild('TotalHT', $order->getTotal_paid_tax_excl()); // Total HT pour ce taux de TVA
        $tva->addChild('TauxTva', '20.00'); // Taux de TVA, exemple statique ici
        $tva->addChild('MtTva', $order->getTotal_discounts_tax_incl()); // Montant de la TVA pour ce taux

        // Enregistrer le fichier XML
        $xmlFileName = __DIR__ . '/../../Data/Orders/commande_' . $order->getId() . '_' . time() . '.xml'; // Spécifier le chemin et le nom du fichier
        $xml->asXML($xmlFileName);

        return new Response('<pre>OK</pre>');
    }

    /**
     * Installe tous les éléments nécessaires à l'utilisation du bundle
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    #[Route('/install')]
    public function installAction(Request $request): Response
    {
        if (!Diffusion::getByPath(self::DIFFUSION_PATH)) {
            $diffusion = new Diffusion();
            $diffusion->setParentID(WebsiteSetting::getByName('folderDiffusion')->getData());
            $diffusion->setKey(self::DIFFUSION_NAME);
            $diffusion->setName(self::DIFFUSION_NAME);
            $diffusion->setPublished(true);
            $diffusion->save();
            $lstConfig = $diffusion->getConfig();
        } else {
            $diffusion = Diffusion::getByPath(self::DIFFUSION_PATH);
        }
        foreach (self::PARAMETERS as $key => $name) {
            //Nomenclature BL
            if (!Config::getByPath(self::DIFFUSION_PATH . '/' . $key) && $key !== $name) {
                $config = new Config();
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

    /**
     * Vérifie la validité d'un code EAN-13.
     *
     * Cette fonction prend en entrée un code EAN-13 et vérifie s'il est valide en
     * suivant les règles de validation de l'EAN-13. Le code EAN-13 est composé de
     * 13 chiffres, et le dernier chiffre est un chiffre de contrôle calculé en
     * fonction des 12 premiers chiffres.
     *
     * @param string $ean Le code EAN-13 à vérifier. Il doit être composé uniquement
     *                    de chiffres et ne doit contenir aucun espace ni caractère
     *                    non numérique.
     *
     * @return string|null Retourne le code EAN-13 s'il est valide, sinon renvoie null.
     *
     * @example
     * ```php
     * $ean = "123456789012"; // Remplacez ceci par le code EAN-13 que vous souhaitez vérifier
     * $result = validateEAN13($ean);
     * if ($result !== null) {
     *     echo "Code EAN-13 valide : $result";
     * } else {
     *     echo "Code EAN-13 invalide";
     * }
     * ```
     */
    function validateEAN13(string $ean): array|string|null
    {
        // Supprimer les espaces et caractères non numériques
        $ean = preg_replace("/[^0-9]/", "", $ean);

        // Vérifier la longueur du code EAN-13
        if (strlen($ean) != 13) {
            return null;
        }

        // Calculer la somme des chiffres impairs et des chiffres pairs
        $sumOdd = 0;
        $sumEven = 0;
        for ($i = 0; $i < 12; $i++) {
            if ($i % 2 == 0) {
                $sumEven += (int)$ean[$i];
            } else {
                $sumOdd += (int)$ean[$i];
            }
        }

        // Calculer le chiffre de contrôle
        $checksum = (10 - (($sumOdd * 3 + $sumEven) % 10)) % 10;

        // Vérifier si le chiffre de contrôle correspond au dernier chiffre du code EAN-13
        if ((int)$ean[12] === $checksum) {
            return $ean;
        } else {
            return null;
        }
    }

    /**
     * Génère les éléments d'adresse pour un objet d'adresse donné.
     *
     * @param SimpleXMLElement $parentXML L'élément XML parent auquel attacher les éléments d'adresse.
     * @param OrderAddress $address L'objet d'adresse à partir duquel obtenir les informations.
     */
    private function addAddressElements(SimpleXMLElement $parentXML, OrderAddress $address): void
    {
        $parentXML->addChild('Nom', $address->getLastname());
        $parentXML->addChild('Prenom', $address->getFirstname());
        $parentXML->addChild('Adr1', $address->getAddress1());
        $parentXML->addChild('Adr2', $address->getAddress2());
        $parentXML->addChild('Adr3', $address->getAddress3());
        $parentXML->addChild('CP', $address->getPostcode());
        $parentXML->addChild('Ville', $address->getCity());
        $parentXML->addChild('Pays', /* Récupérer le nom complet du pays */);
        $parentXML->addChild('PaysISO', $address->getCountry());
        $parentXML->addChild('Tel', $address->getPhone());
        $parentXML->addChild('Gsm', $address->getPhone2());
    }
}
