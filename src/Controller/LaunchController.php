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
use bundles\ecGinkoiaBundle\src\ecProduct;
use bundles\ecGinkoiaBundle\src\ecTimer;
use bundles\ecMiddleBundle\Services\Outils;

class LaunchController extends FrontendController
{
    /**
     * @Route("/launchecGinkoia", name="launchecGinkoia")
     */
    public function indexAction(Request $request)
    {
        //récupérer les get/post dans un tableau
        $data = $request->query->all();
        
        $function = $data['name'] ?? '';
        $class = $data['class'] ?? 'bundles\\ecGinkoiaBundle\\src\\connector';
        if (false === strpos($class, 'bundles\\ecGinkoiaBundle\\src\\')) {
            $class = 'bundles\\ecGinkoiaBundle\\src\\' . $class;
        }
        $param = $data['param'] ?? null;
        if (!is_array($param)) {
            $param = [$param];
        }
        
        //lancer fonction si elle existe
        if (!class_exists($class)) {
            return new JsonResponse(['errors' => vsprintf('Class %s does not exist', [$class]),], 200);
        }
        $object = new $class();
        if (!method_exists($object, $function)) {
            return new JsonResponse(['errors' => vsprintf('M^$ethod %s does not exist in class %s', [$function, $class]),], 200);
        }
        $reps = $object->$function(...$param);
        $collect = ecCollect::get();
        $infos = $collect->getInfos();
        $returns = $collect->getReturns();
        $erros = $collect->getErrors();
        $warnings = $collect->getWarnings();
        $times = ecTimer::get()->getTimeLine();
        
        return new JsonResponse(['reponse' => $reps, 'infos' => $infos, 'returns' => $returns, 'warning' => $warnings, 'errors' => $erros, 'times' => $times], 200);
    }

    public function cronGetFile(array $params)
    {
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START : '.json_encode($params), 3);
        $class = new ecProduct();
        return $class->cronGetFile($params);
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END : '.json_encode($params), 3);
    }

    public function cronGetFileStock(array $params)
    {
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START : '.json_encode($params), 3);
        $class = new ecProduct();
        return $class->cronGetFileStock($params);
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END : '.json_encode($params), 3);
    }

    public function cronGetFilePrice(array $params)
    {
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START : '.json_encode($params), 3);
        $class = new ecProduct();
        return $class->cronGetFilePrice($params);
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END : '.json_encode($params), 3);
    }

    public function cronFillCatalog(array $params)
    {
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START : '.json_encode($params), 3);
        $class = new ecProduct();
        return $class->cronFillCatalog($params);
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END : '.json_encode($params), 3);
    }

    public function cronUpdateStock(array $params)
    {
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START : '.json_encode($params), 3);
        $class = new ecProduct();
        return $class->cronUpdateStock($params);
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END : '.json_encode($params), 3);
    }

    public function cronUpdatePrice(array $params)
    {
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START : '.json_encode($params), 3);
        $class = new ecProduct();
        return $class->cronUpdatePrice($params);
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END : '.json_encode($params), 3);
    }

    public function cronSyncGinkoia(array $params)
    {
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START : '.json_encode($params), 3);
        $class = new ecProduct();
        return $class->cronSyncGinkoia($params);
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END : '.json_encode($params), 3);
    }

    public function cronUpdateFluctuMinus(array $params)
    {
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - START : '.json_encode($params), 3);
        $class = new ecProduct();
        return $class->cronUpdateFluctuMinus($params);
        // Outils::addLog('(EcGinkoia ('.__FUNCTION__.') :' . __LINE__ . ') - END : '.json_encode($params), 3);
    }
}
