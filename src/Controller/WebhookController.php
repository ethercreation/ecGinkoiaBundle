<?php

namespace bundles\ecGinkoiaBundle\Controller;

use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject;
use Pimcore\Tool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use bundles\ecGinkoiaBundle\src\connector;
use bundles\ecGinkoiaBundle\src\ecCollect;
use bundles\ecGinkoiaBundle\src\ecTimer;
use bundles\ecMiddleBundle\Services\Outils;
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
use SimpleXMLElement;
use Locale;
// use Pimcore\Localization\Service;
use Pimcore\Model\DataObject\PreGetValueHookInterface;

class WebhookController extends FrontendController
{
    /**
     * @Route("/webhookecGinkoia", name="webhookecGinkoia")
     */
    public function indexAction(Request $request)
    {
        $data = $request->query->all();

        $retour = true;
        if (isset($data['id_order'])) {
            $obj = DataObject::getById($data['id_order']);
            $retour = $this->createOrder($obj, true);

        }

        return new JsonResponse(['id_order' => $data['id_order'] ?? '', 'retour' => $retour], 200);
    }

    public function hookUpdateOrderHistory($params, $diffSpe = 0)
    {        
        $objHook = $params['orderHistory'];
        $objHook->setHideUnpublished(false);
        $Obj = $objHook->getObjectHistory();
        $stat = $objHook->getOrder_state();
        $configPaymentValide = json_decode(Dataobject::getByPath('/Config/paiement_valide')->getValeur(), true);
        if (!in_array($stat->getId(), $configPaymentValide)) {
            return true;
        }

        $diffusion = Dataobject::getByPath('/Diffusion/ecGinkoia');
 
        if ($Obj->getClassName() == 'order') {
            foreach ($Obj->getCrossid() as $crossid) {
                if ($diffusion->getId() != $crossid->getElementId()) {
                    continue;
                }
                
                $this->createOrder($Obj);
            }
        }
       
    }

    public function createOrder($order, $simulation = false)
    {
        if (null === $order) {
            return false;
        }

        $customer = $order->getCustomer();
        $address_delivery = $order->getAddress_delivery();
        $address_invoice = $order->getAddress_invoice();
        $dirDepot = dirname(__FILE__).'/../../files/export/';
        $dirArchive = dirname(__FILE__).'/../../files/export/archive/';
        $file = 'commande_'.$order->getId_order().'_'.date('U').'.xml';
        
        if (!is_dir($dirArchive)) {
            mkdir($dirArchive);
        }

        $portTTC = $order->getTotal_shipping_tax_incl();
        $portHT = $portTTC / 1.2;

        $totalHT = $mtTVA = 0;
        $order_ligne = $lstDataTVA = [];
        foreach ($order->getOrderdetail() as $detail) {
            $tva = sprintf("%.2f", $detail->getTax_rate());

            $unit_price_tax_incl = $detail->getUnit_tax_incl();
            $unit_price_tax_excl = $detail->getUnit_tax_incl() * (100 / (100 + $tva));

            $total_price_tax_incl = $detail->getTotal_tax_incl();
            $total_price_tax_excl = $detail->getTotal_tax_incl() * (100 / (100 + $tva));
            $order_ligne[] = [
                'Ligne' => [
                    'TypeLigne' => 'Ligne',
                    'Code' => $detail->getReference(),
                    'CodeEAN' => $detail->getEan13(),
                    'Designation' => $detail->getName(),
                    'PUBrutHT' => sprintf("%.2f", $unit_price_tax_excl),
                    'PUBrutTTC' => sprintf("%.2f", $unit_price_tax_incl),
                    'Qte' => $detail->getQuantity(),
                    'PUHT' => sprintf("%.2f", $unit_price_tax_excl),
                    'PUTTC' => sprintf("%.2f", $unit_price_tax_incl),
                    'TxTva' => $tva,
                    'PXHT' => sprintf("%.2f", $total_price_tax_excl),
                    'PXTTC' => sprintf("%.2f", $total_price_tax_incl),
                ]
            ];
            $lstDataTVA[$tva]['totalHT'] = ($lstDataTVA[$tva]['totalHT'] ?? 0) + $total_price_tax_excl;
            $lstDataTVA[$tva]['totalTVA'] = ($lstDataTVA[$tva]['totalTVA'] ?? 0) + ($total_price_tax_incl - $total_price_tax_excl);
        }

        
        $totalHT = $MtTva = 0;
        $tva_ligne = [];
        foreach ($lstDataTVA as $tva => $info) {
            $tva_ligne[] = [
                'TVA' => [
                    'TotalHT' => sprintf("%.2f", $info['totalHT']),
                    'TauxTva' => sprintf("%.2f", $tva),
                    'MtTva' => sprintf("%.2f", $info['totalTVA']),
                ]
            ];
            $totalHT += $info['totalHT'];
            $MtTva += $info['totalTVA'];
        }
        
        $postData = [
            'CommandeNum' => $order->getId_order(),
            'CommandeId' => substr($order->getId_order(), 0, 9),
            'CommandeDate' => $order->getDate_add()->format('Y-m-d H:i:s'),
            'Statut' => 'PAYE', // PAYE // CHEQUE
            'ModeReglement' => '', //Doit être vide si l'on utilise la balise reglements
            'DateReglement' => $order->getDate_add()->format('Y-m-d H:i:s').'.00',
            'Export' => 0,
            'Client' => [
                'CodeClient' => substr($customer->getId_customer(), 0, 9),
                'Email' => ('client@zalando.fr' == $customer->getEmail()) ? ($customer->getId_customer.$customer->getEmail()) : $customer->getEmail(),
                'AddressFact' => [
                    'Civ' => '',
                    'Nom' => trim($address_invoice->getLastname()) ?: $address_delivery->getLastname(),
                    'Prenom' => trim($address_invoice->getFirstname()) ?: $address_delivery->getFirstname(),
                    'Ste' => substr(($address_invoice->getCompany() ?: $address_delivery->getCompany()), 0, 60),
                    'Adr1' => $address_invoice->getAddress1() ?: $address_delivery->getAddress1(),
                    'Adr2' => $address_invoice->getAddress2() ?: $address_delivery->getAddress2(),
                    'Adr3' => $address_invoice->getAddress3() ?: $address_delivery->getAddress3() ?: '',
                    'CP' => $address_invoice->getPostcode() ?: $address_delivery->getPostcode(),
                    'Ville' => $address_invoice->getCity() ?: $address_delivery->getCity(),
                    'Pays' => Locale::getDisplayRegion('-'.($address_invoice->getCountry() ?: $address_delivery->getCountry()), 'fr'),
                    'PaysISO' => $address_invoice->getCountry() ?: $address_delivery->getCountry(),
                    'Tel' => $address_invoice->getPhone() ?: $address_delivery->getPhone() ?: '',
                    'Gsm' => $address_invoice->getPhone2() ?: $address_delivery->getPhone2() ?: '',
                    'Fax' => '',
                ],
                'AddressLivr' => [
                    'Civ' => '',
                    'Nom' => trim($address_delivery->getLastname()) ?: $address_invoice->getLastname(),
                    'Prenom' => trim($address_delivery->getFirstname()) ?: $address_invoice->getFirstname(),
                    'Ste' => substr(($address_delivery->getCompany() ?: $address_invoice->getCompany()), 0, 60),
                    'Adr1' => $address_delivery->getAddress1() ?: $address_invoice->getAddress1(),
                    'Adr2' => $address_delivery->getAddress2() ?: $address_invoice->getAddress2(),
                    'Adr3' => $address_delivery->getAddress3() ?: $address_invoice->getAddress3() ?: '',
                    'CP' => $address_delivery->getPostcode() ?: $address_invoice->getPostcode(),
                    'Ville' => $address_delivery->getCity() ?: $address_invoice->getCity(),
                    'Pays' => Locale::getDisplayRegion('-'.($address_delivery->getCountry() ?: $address_invoice->getCountry()), 'fr'),
                    'PaysISO' => $address_delivery->getCountry() ?: $address_invoice->getCountry(),
                    'Tel' => $address_delivery->getPhone() ?: $address_invoice->getPhone() ?: '',
                    'Gsm' => $address_delivery->getPhone2() ?: $address_invoice->getPhone2() ?: '',
                    'Fax' => '',
                ],
            ],
            'Colis' => [
                'Numero' => '',
                'Transporteur' => $order->getCarrier() ?: '',
                'MagasinRetrait' => '',
            ],
            'Lignes' => $order_ligne,
            'SousTotalHT' => sprintf("%.2f", $totalHT),
            'TVAS' => $tva_ligne,
            'FraisPort' => sprintf("%.2f", $portTTC),
            'TotalHT' => sprintf("%.2f", $totalHT + $portHT),
            'MontantTVA' => sprintf("%.2f", $MtTva + ($portTTC - $portHT)),
            'TotalTTC' => sprintf("%.2f", $order->getTotal_paid_tax_incl()),
            'Netpayer' => sprintf("%.2f", $order->getTotal_paid_tax_incl()),
            'Reglements' => [
                [
                    'Reglement' => [
                        'Mode' => $order->getPayment() ?: 'card',
                        'MontantTTC' => sprintf("%.2f", $order->getTotal_paid_tax_incl()),
                        'Date' => $order->getDate_add()->format('Y-m-d H:i:s'),
                    ]
                ]
            ],
        ];
        
        if ($simulation) {
            return $postData;
        }

        $xml_postData = new SimpleXMLElement('<?xml version="1.0"?><Commande></Commande>');
        $xml_sxe = Outils::arrayToXML($postData, $xml_postData);
        $pretty_xml = self::prettyXML($xml_sxe->asXML());
        file_put_contents($dirDepot.$file, $pretty_xml);
        file_put_contents($dirArchive.$file, $pretty_xml);




        return true;
    }
    
    public static function prettyXML($xml_text)
    {
        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $doc->loadXML($xml_text);
        return $doc->saveXML();
    }
}