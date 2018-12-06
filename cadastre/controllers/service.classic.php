<?php
/**
* @package   lizmap
* @subpackage cadastre
* @author    3liz
* @copyright 2016 3liz
* @link      http://3liz.com
* @license Mozilla Public License : http://www.mozilla.org/MPL/
*/

class serviceCtrl extends jController {

    /**
    * Get PDF generated by QGIS Server Cadastre plugin
    * @param project Project key
    * @param repository Repository key
    * @param layer Name of the Parcelle layer
    * @param parcelle ID of the parcelle ( field geo_parcellle )
    * @param type Type of export: parcelle or proprietaire
    */
    function getCadastrePdf() {

        $project = $this->param('project');
        $repository = $this->param('repository');

        $rep = $this->getResponse('json');

        if( !jAcl2::check("cadastre.acces.donnees.proprio") ){
            $rep->data = array('status'=>'fail', 'message'=>'Vous n\'avez pas les droits pour voir les données de propriété.');
            return $rep;
        }

        if( !preg_match('#^cadastre#i', $project) ){
            $rep->data = array('status'=>'fail', 'message'=>'This is not a cadastre project. Project key must begins with cadastre');
            return $rep;
        }

        $p = lizmap::getProject($repository.'~'.$project);
        if( !$p ){
            $rep->data = array('status'=>'fail', 'message'=>'A problem occured while loading project with Lizmap');
            return $rep;
        }

        $parcelleLayer = $this->param('layer');
        $parcelleId = $this->param('parcelle');
        $type = $this->param('type');
        if(!$parcelleLayer or !$parcelleId or !$type){
            $rep->data = array('status'=>'fail', 'message'=>'layer, parcelle and type parameters are mandatory');
            return $rep;
        }
        jClasses::inc('cadastre~lizmapCadastreRequest');
        if($type == 'fiche'){
            $creq = 'getHtml';
            jLog::log($creq);
        }else{
            $creq = 'createPdf';
        }
        $request = new lizmapCadastreRequest(
            $p,
            array(
                'service'=>'CADASTRE',
                'request'=>$creq,
                'layer'=> $parcelleLayer,
                'parcelle'=> $parcelleId,
                'type'=> $type
            )
        );
        $result = $request->process();

        // Check errors
        if($result->mime == 'text/xml'){
            $rep->data = array('status'=>'fail', 'message'=> trim(preg_replace( "#\n#", '', strip_tags($result->data))));
            return $rep;
        }

        if($type == 'fiche'){
            $rep = $this->getResponse('htmlfragment');
            $rep->addContent($result->data);
            return $rep;
        }

        // Get created PDFs;
        $data = $result->data;
        $pdfs = array();
        $tok = Null;
        foreach( $data->data->tokens as $token ){
            $tok = $token;
            $request = new lizmapCadastreRequest(
                $p,
                array(
                    'service'=>'CADASTRE',
                    'request'=>'getPdf',
                    'token'=> $token
                )
            );
            $result = $request->process();
            if( $result->mime != 'application/pdf'){
                continue;
            }
            $pdfs[$token] = $result->data;
        }
        if( count($pdfs) == 1 ){
            $rep = $this->getResponse('binary');
            $rep->mimeType = 'application/pdf';
            $rep->content = $pdfs[$tok];
            $rep->doDownload  =  false;
            $rep->outputFileName = 'cadastre_' . $tok . '.pdf';
        }else if(count($pdfs) == 0){
            $rep = $this->getResponse('text');
            $rep->content = 'Erreur de création du relevé.';
            return $rep;
        }else{
            $rep = $this->getResponse('zip');
            $rep->zipFilename='releves_cadastre.zip';
            foreach( $pdfs as $token=>$pdf ){
                $rep->content->addContentFile('cadastre_' . $token . '.pdf', $pdf);
            }
        }

        return $rep;
    }


    /**
     * Autocompletion search
     *
    */
    function autocomplete() {

        $rep = $this->getResponse('json');

        $term = $this->param('term');
        $field = $this->param('field', 'voie');
        $commune = $this->param('commune');
        $voie = $this->param('voie');
        $limit = $this->intParam('limit', 30);

        $autocomplete = jClasses::getService('cadastre~search');
        $result = $autocomplete->getData( $term, $field, $commune, $voie, $limit );

        $rep->data = $result;

        return $rep;
    }

    /**
     * Get total extent for road or owner
     *
    */
    function extent() {

        $rep = $this->getResponse('json');

        $field = $this->param('field', 'voie');
        $value = $this->param('value');

        $autocomplete = jClasses::getService('cadastre~search');
        $result = $autocomplete->getDataExtent( $field, $value );

        $rep->data = $result;

        return $rep;
    }

}

