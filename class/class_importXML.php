<?php
require_once("class_classJM.php");
require_once("class_contexte.php");
require_once("class_chapitre.php");

class ImportXML extends ClassJM{


  protected $xml_chemin;
  protected $contexte;
  protected $oChapitre;
  
  
  private $baliseHtml = array(
                    "SUP" => "sup",
                    "SUB" => "sub",
                    "INF" => "inf",
  );
  
  private $baliseAutoFermante = array(
                    "br" => "br"
  );
  
  private $baliseAIgnorer = array("AIgnorer","inconnu", "table", "tr", "td", "th", "tbody", "thead");
  
  private $baliseContenu = array("section","enbref","div","avantdepartir","surplace","evenements",
                                "internet","circuit","etape"
  );
  
  private $baliseNonConsidere = array(
                            "REF_INTERNES"
  );

  function __construct($argument = -1){
    if($argument!=-1){
      $this->createNewXml($argument);
    }    
    $this->contexte = new Contexte();
  }
  
  
  public function getDateExecution(){
    return $this->xml_execution_date;
  }
  
  public function getNomFichier(){
    return basename($this->getCheminXml());
  }
  
  public function getCheminXml(){
    return $this->xml_chemin;
  }
  
  public function getNomDossierTravail(){
    return str_replace($this->getNomFichier(),'',$this->getCheminXml());
  }
  
  public function getIdPartenaire(){
    return $this->xml_idPartenaire;
  }
  
  public function getIdHachette(){
    return $this->xml_idHachette;
  }
  
  public function getNomPartenaire(){
    return $this->oPartenaire->getNom();
  }
  
  public function getDossierImages(){
    return $this->getNomDossierTravail().$this->getDossierImagesRelatif();
  }
  
  public function getDossierImagesRelatif(){
    return 'Images/';
  }
  
  public function getDossierVisuels(){
    return $this->getNomDossierTravail().'Visuels/';
  }
  
  public function getDossierPdf(){
    return $this->getNomDossierTravail().'PDF/';
  }
  
  public function setDateExecution($value){
    $this->xml_execution_date = $value;
  }
  
  public function setIdPartenaire($value){
    $this->xml_idPartenaire = $value;
  }
  
  public function setIdHachette($value){
    $this->xml_idHachette = $value;
  }
  
  public function setCheminXml($value){
    $this->xml_chemin = $value;
  }
  
  


  /***********************************
   * 
   *      GESTION XML
   * 
   ***********************************/

  
  function traiteXml(){
    // Inutile ici, mais permet d'ajouter des actions avant et apr�s l'�xecution du XML 
    // sans polluer la fonction lectureXML

    $this->oChapitre = new Chapitre();
    $this->lectureXml();
    return $this->oChapitre;
  }

   protected function createNewXml($lien){
    if(is_file($lien)){
      $this->setCheminXml($lien);
    }else{
      echo "Erreur dans le lien ".$lien;
    }
  }
  
  function lectureXml(){
    $dom = new DomDocument();
    if(!$dom->load($this->getCheminXml())){
      return false;
    }
    $elements = $dom->getElementsByTagName('DOCUMENT');
    $element = $elements->item(0);
    $this->traiteBalise($element, false);
    $this->traiteBalise($element, false);
  }

  private function traiteBalise($noeud, $withBaliseEntourante = true){
    // On r�cup�re le nom de la balise
    $nomBalise = $noeud->nodeName;
    $nomFonction = 'balise'.ucfirst($nomBalise);
    //echo 'Nom balise : '.$nomBalise.'<br/>';
    //echo '<pre>';print_r(callstack());echo '</pre>';
    // On r�cup�re les attributs de la balise
    $attr = array();
    if($noeud->hasAttributes()){
      $attributes = $noeud->attributes;
      foreach ($attributes as $index => $domobj)
      {
          $attr[$domobj->name]=$domobj->value;
      }
    }
    // On ajoute le nom de cette balise au contexte
    $this->contexte->AjouterAuDessusPile($nomBalise, $attr);
    // Il faut savoir que le texte contenu dans une balise est consid�rer comme un fils
    // (c'est inclus dans la balise #text du DOM)
    // Donc on ne rentrera pas dans le if ci dessous uniquement si l'on a que du texte ou une balise auto-fermante
    if($noeud->hasChildNodes()){
        // On regarde si on a un traitement sp�cial pour cette balise
        // Pour cr�er une gestion sp�cifique il suffit de cr�er une function baliseNomDeLaBalise()
        // Attention le retour des functions sp�cifiques doit etre un tableau avec une valeur de d�but et de fin, le contenu sera g�r� avec la suite
        if (method_exists($this,$nomFonction)){
          $resultat = $this->$nomFonction($noeud);
          $accumulateur = $resultat['debut'];
          $finAccumulateur =$resultat['fin'];
          // Ceci permet aux fonctions de g�rer elle m�me leur fils.
          // A ce moment la il faut les ignorer ici cars ils ont d�ja �taient trait�s.
          if(isset($resultat['gereLesNoeudsFils']) && $resultat['gereLesNoeudsFils']){
            $ignorer = true;
          }
        }elseif(in_array($nomBalise, array_flip($this->baliseHtml))){
            // On g�re les balises html simple tel que le bold, l'ital....
            $accumulateur = '<'.$this->baliseHtml[$nomBalise].'>';
            $finAccumulateur = '</'.$this->baliseHtml[$nomBalise].'>';
        }elseif(in_array($nomBalise, $this->baliseNonConsidere)){
            // Balise qui doivent �tre ignor�e, on ne fait rien....
            $accumulateur = '';
            $finAccumulateur = '';
        }elseif(in_array($nomBalise, $this->baliseAIgnorer)){
            // Balise qui doivent �tre ignor�e, on ne fait rien....
            $accumulateur = '';
            $finAccumulateur = '';
            $ignorer = true;
        }else{
           // Sinon on cr� un div avec la class = au nom de la balise
          if($withBaliseEntourante){
            $accumulateur = '<div class="'.$nomBalise.'">';
            $finAccumulateur = '</div>';
          }else{
            $accumulateur = '';
            $finAccumulateur = '';
          }
        }
        $enfants_niv1 = $noeud->childNodes;
        if(!isset($ignorer) || $ignorer===false){
          foreach($enfants_niv1 as $enfant) // Pour chaque enfant, on v�rifie�
          {
            //echo '------------- Nom enfant : '.$enfant->nodeName.'<br/>';
             $accumulateur .= $this->traiteBalise($enfant);
          }
        }
        $accumulateur .= $finAccumulateur;
        $retour = $accumulateur;
      }else{
        // Ici on est dans le cas de texte simple ou de balise auto-fermante
        if (method_exists($this,$nomFonction)){
          $retourTemp = $this->$nomFonction($noeud);
          $retour = $retourTemp['debut'].$retourTemp['fin'];
        }elseif(in_array($nomBalise, $this->baliseAutoFermante)){
            $retour = '<'.$this->baliseAutoFermante[$nomBalise].'/>';
        }else{
          $retour = $this->textForXml($noeud->nodeValue);
        }
      }
      
    // On retire le nom de cette balise au contexte
    $this->contexte->retirerDernierPile();
    // Retour
    return $retour;
  }
  
  private function baliseTitre($noeud){
    $sectionMere = $this->contexte->getLastContexteWithName('SECTION');
    //print_r($sectionMere);
    if(isset($sectionMere['attribut']['Niv'])){
      $niv = $sectionMere['attribut']['Niv']+1;
    }else{
      $niv = 7;
    }
    $retour['debut'] = '<h'.$niv.' class="niveau'.$niv.'">';
    $retour['fin'] = '</h'.$niv.'>';
    $retour['gereLesNoeudsFils'] = false;
    //print_r($retour).'<br/>';
    return $retour;
  }
  
  private function baliseI($noeud){
    $sectionMere = $this->contexte->getLastContexteWithName('SECTION');
    // gestion des b et i en cascade
    if($this->contexte->isInContexte('B')){
      $retour['debut'] = '</span><span class="bi">';
      $retour['fin'] = '</span><span class="b">';
    }else{
      $retour['debut'] = '<span class="i">';
      $retour['fin'] = '</span>';
    }
    $retour['gereLesNoeudsFils'] = false;
    //print_r($retour).'<br/>';
    return $retour;
  }
  
  private function baliseB($noeud){
    $sectionMere = $this->contexte->getLastContexteWithName('SECTION');
    // gestion des b et i en cascade
    if($this->contexte->isInContexte('I')){
      $retour['debut'] = '</span><span class="bi">';
      $retour['fin'] = '</span><span class="i">';
    }else{
      $retour['debut'] = '<span class="b">';
      $retour['fin'] = '</span>';
    }
    $retour['gereLesNoeudsFils'] = false;
    //print_r($retour).'<br/>';
    return $retour;
  }
  
  private function baliseExergue($noeud){
    if($noeud->hasAttributes()){
      $attributes = $noeud->attributes;
      foreach ($attributes as $index => $domobj)
      {
          switch($domobj->name){
            case 'Type':
            $type = $domobj->value;
            break;
          }
      }
    }
    $retour['debut'] = '<div class="'.$type.'">';
    $retour['fin'] = '</div>';
    $retour['gereLesNoeudsFils'] = false;
    //print_r($retour).'<br/>';
    return $retour;
  }
  
  private function baliseRef_externes($noeud){
    $retour['debut'] = '<dfn class="N_article">';
    $retour['fin'] = '</dfn>';
    $retour['gereLesNoeudsFils'] = false;
    //print_r($retour).'<br/>';
    return $retour;
  }
  
  private function baliseTableau($noeud){
    $attributes = $noeud->attributes;
    foreach ($attributes as $index => $domobj)
    {
        switch($domobj->name){
          case "Id":
            $id = $domobj->value;
            break;
          default:
            
            break;
        }
    }

    $retour['debut'] = '';
    $retour['fin'] = '';
    $retour['gereLesNoeudsFils'] = false;
    //print_r($retour).'<br/>';
    return $retour;
  }

    /*private function baliseTable($noeud){
        $retour['debut'] = '<table>';
        $retour['fin'] = '</table>';
        $retour['gereLesNoeudsFils'] = false;
        return $retour;
    }

    private function baliseTr($noeud){
        $retour['debut'] = '<tr>';
        $retour['fin'] = '</tr>';
        $retour['gereLesNoeudsFils'] = false;
        return $retour;
    }

    private function baliseTd($noeud){
        $retour['debut'] = '<td>';
        $retour['fin'] = '</td>';
        $retour['gereLesNoeudsFils'] = false;
        return $retour;
    }

    private function baliseTh($noeud){
        $retour['debut'] = '<th>';
        $retour['fin'] = '</th>';
        $retour['gereLesNoeudsFils'] = false;
        return $retour;
    }*/

  
  private function baliseDoc_dev($noeud){

    $contenu = $this->getContenuBalise($noeud);

    $retour['debut'] = '';
    $retour['fin'] = '';
    $retour['gereLesNoeudsFils'] = true;
    
    // Gestion des liens internes
    $contenu = preg_replace('#\* (([0-9]){3})#isU', '<dfn class="main">a</dfn><a href="$1.html">$1</a>', $contenu);
    $contenu = str_replace('-&gt;','<dfn class="fleche">symbole</dfn>', $contenu);
    $this->oChapitre->setContenu($contenu);
    //print_r($retour).'<br/>';
    return $retour;
  }
  
  private function baliseSujet_article($noeud){

    $this->oChapitre->setTitre($this->getContenuBalise($noeud));

    $retour['debut'] = '';
    $retour['fin'] = '';
    $retour['gereLesNoeudsFils'] = true;
    //print_r($retour).'<br/>';
    return $retour;
  }
  
  private function baliseEnum($noeud){
    //echo '<pre>';print_r($this->contexte->getFullContexte());'</pre>';
    $post = '';
    if($this->contexte->isInContexte('EXERGUE')){
      $contexte = $this->contexte->getLastContexteWithName('EXERGUE');
      if(isset($contexte['attribut']['Type'])){
        $post = '_'.$contexte['attribut']['Type'];
        //echo '<pre>';print_r($this->contexte->getFullContexte());'</pre>';
      }
    }
    $retour['debut'] = '<div class="enum'.$post.'">';
    $retour['fin'] = '</div>';
    $retour['gereLesNoeudsFils'] = false;
    //print_r($retour).'<br/>';
    return $retour;
  }
  
  private function baliseTexte($noeud){
    $acc = $this->getContenuBalise($noeud);
    $before = '';
    $after = '';
    if(substr(strip_tags($acc),0,2)=='- '){
      // si une balise texte commence par "- " on la traite comme si il y avait une balise enum englobante
      $retourTemp = $this->baliseEnum($noeud);
      $before = $retourTemp['debut'];
      $after = $retourTemp['fin'];
    }
    $retour['debut'] = $before.'<p>'.$acc;
    $retour['fin'] = '</p>'.$after;
    $retour['gereLesNoeudsFils'] = true;
    return $retour;
  }
  
  private function baliseRubrique($noeud){

    $this->oChapitre->setRubrique($this->getContenuBalise($noeud));

    $retour['debut'] = '';
    $retour['fin'] = '';
    $retour['gereLesNoeudsFils'] = true;
    //print_r($retour).'<br/>';
    return $retour;
  }
  
  private function baliseImage($noeud){
    $attributes = $noeud->attributes;
    foreach ($attributes as $index => $domobj)
    {
        switch($domobj->name){
          case "Id":
            $id = $domobj->value;
            break;
          case "Format":
            $format = $domobj->value;
            break;
          case "Fichier":
            $fichier = $domobj->value;
            break;
          default:
            
            break;
        }
    }
    $cheminImage = $this->getDossierImages().$fichier.'.'.$format;
    $cheminImageRelatif = 'img/'.$fichier.'.'.$format;
    //echo '<br/>image '.$cheminImage;
    if(is_file($cheminImage)){
      $retour['debut'] = '<p><img src="'.$cheminImageRelatif.'" alt="image"/></p>';
      $retour['fin'] = '';
    }else{
      $retour['debut'] = '';
      $retour['fin'] = '';
      echo '<br/>ERREUR image manquante '.$cheminImage;
    }
    
    $retour['gereLesNoeudsFils'] = true;
    //print_r($retour).'<br/>';
    return $retour;
  }
  
  private function getContenuBalise($noeud, $baliseEntourante = false){
    $enfants_niv1 = $noeud->childNodes;
    $acc = '';
    foreach($enfants_niv1 as $enfant) // Pour chaque enfant, on v�rifie�
    {
      $acc .= $this->traiteBalise($enfant, false);
    }
    return $acc;
  }
 
 
}
?>