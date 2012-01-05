<?php
/**
 * Module de consultation de thesaurus (monolingue, monohi�rarchique)
 * 
 * @package     fab
 * @subpackage  modules
 */
class ThesaurusModule extends DatabaseModule
{
    public function preExecute()
    {
//        if (Utils::isAjax())
//            $this->setLayout('none');	
    }
        
    /**
     * Affiche la hi�rarchie d'un champ s�mantique.
     * 
     * Principe : 
     * - L'utilisateur passe en param�tre le libell� (?Fre=<terme>) d'un champ 
     *   s�mantique (c'est-�-dire un terme pour lequel TG est vide).
     * - On recherche tous les enregistrements pour lesquels MT=<terme>
     * - On parcourt toutes les r�ponses obtenues pour constituer un tableau 
     *   qui pour chaque TG trouv� contient un tableau contenant les TS.
     * - On utilise la m�thode {@link makeHierarchy()} qui reconstitue la 
     *   hi�rarchie compl�te du champ s�mantique demand�e � partir de ce tableau.
     * - On appelle le template indiqu� par la m�thode {@link getTemplate()} 
     *   pour afficher le r�sultat.
     */
    public function actionHierarchy()
    {
        // Ouvre la base de donn�es
        $this->openDatabase();

        // D�termine la recherche � ex�cuter        
        $this->equation=$this->getEquation();

        // R�cup�re le libell� exact du terme demand�
        if (! $this->select($this->equation, 1, 1, '+'))
        {
            $this->showNoAnswer("La requ�te $this->equation n'a donn� aucune r�ponse.");
            return;
        }
        $term=$this->selection['Fre'];
        
        // Lance une requ�te MT:<terme>, g�re le cas aucune r�ponse
        $this->equation='MT:' . $this->termToQuery($term);
        if (! $this->select($this->equation, -1, 1, '+'))
        {
            $this->showNoAnswer("Le terme $term ne d�signe pas un champ s�mantique du th�saurus.");
            return;
        }
        
        // Constitue le tableau initial TG -> liste des TS
        $hierarchy=array();
        foreach($this->selection as $record)
        {
            //echo $record['Fre'], ' TG : ', $record['TG'], '<br />';
            Utils::arrayAppendKey($hierarchy, $record['TG'], $record['Fre']);
        }    

        // Reconstitue la hi�rarchie du terme
        $this->makeHierarchy($hierarchy[$term], $hierarchy);

        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
            throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
        
        // Ex�cute le template
        Template::run
        (
            $template,
            array('hierarchy'=>$hierarchy)
        );  
    }
    
    /**
     * Reconstitue la hi�rarchie compl�te d'un champ s�mantique.
     * 
     * @param array $term un tableau d�signant le terme dont on veut reconstituer
     * la hi�rarchie. 
     * @param array $allTerms un tableau contenant tous les termes.
     * En sortie, ne contiendra plus que le terme recherch�.
     * 
     * Important :
     * - $term doit obligatoirement �tre l'un des �l�ments du tableau 
     *   <code>$hierarchy</code>. Exemple :
     *   <code>makeHierarchy($t['famille'], $t)</code>
     * - les deux param�trs son "by ref".
     * 
     * @see actionHierarchy()
     */
    private function makeHierarchy(& $term, & $allTerms)
    {
        $t=array();
        foreach((array)$term as $value)
        {
            if (isset($allTerms[$value]))
            {
                $this->makeHierarchy($allTerms[$value], $allTerms);
                $t[$value]=$allTerms[$value];
                unset($allTerms[$value]);
            }
            else
            {
                $t[$value]=$value;
            }
        }
        $term=$t;
    }
    
    /**
     * Nettoie le terme pass� en param�tre pour qu'il puisse �tre utilis� dans 
     * une requ�te pour faire une recherche � l'article.
     * 
     * Le traitement consiste � 
     * - remplacer par un esapce les caract�res reconnus comme op�rateurs
     *   (crochets, parenth�ses, &, +, - 
     * (neutralise les crochets pr�sents dans le terme et l'encadre de crochets)
     * 
     * @param string $term le terme � nettoyer
     * @param string le bout de requ�te obtenu
     */ 
    public function termToQuery($term, $asValue=true, $encode=true)
    {
        $result = str_replace('  ', ' ', trim(strtr($term, '[]()&+-', '       ')));
        if ($encode) $result=urlencode($result);
        if ($asValue) $result = '[' . $result . ']';
        return $result;
    }
}
?>
