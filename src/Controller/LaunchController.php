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
}
