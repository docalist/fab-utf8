<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: DedupModule.php 1050 2009-05-11 16:23:36Z severine.ferron.bdsp $
 */

require_once(dirname(__FILE__).'/DedupMethod.php');
require_once(dirname(__FILE__).'/DedupTokens.php');
require_once(dirname(__FILE__).'/DedupValues.php');
require_once(dirname(__FILE__).'/DedupFirstValue.php');
require_once(dirname(__FILE__).'/DedupYears.php');

/**
 * Ce module permet de rechercher et de traiter des doublons potentiels au sein 
 * d'une base de donn�es.
 * 
 * Il fonctionne � partir d'un lot de notices indiqu�s par une �quation de 
 * recherche. Pour chacune des notices s�lectionn�es, des tests (d�finis dans 
 * le fichier de configuration) vont �tre ex�cut�s.
 * 
 * Une fois ces tests ex�cut�s, on obtient une liste de notices qui sont des 
 * doublons potentiels de la notice �tudi�e.
 * 
 * Le d�doublonnage a proprement parler sera ensuite r�alis� par un humain qui
 * d�cidera si les doublons potentiels d�tect�s sont effectivement des doublons
 * ou non et supprimera ou fusionnera certaines des notices.
 * 
 * @package     fab
 * @subpackage  modules
 */
class DedupModule extends Module
{
    /**
     * La s�lection contenant les notices pour lesquelles on va rechercher
     * des doublons potentiels.
     * 
     * @var Database
     */
    public $selection=null;
    
    /**
     * Permet de lancer une recherche de doublons potentiels pour les notices 
     * s�lectionn�es par l'�quation pass�e en param�tre.
     * 
     * L'action commence par afficher un template � l'utilisateur lui indiquant
     * le nombre de notices s�lectionn�es et lui demandant de confirmer le 
     * lancement du d�doublonnage.
     * 
     * Elle cr�e ensuite une t�che au sein du {@link TaskManager} qui ex�cutera
     * l'action {@link actionDedup() Dedup}.
     *
     * @param string $_equation l'�quation de recherche qui d�finit les notices
     * pour lesquelles on souhaite rechercher des doublons potentiels.
     * 
     * @param bool $confirm un bool�en indiquant si l'action a �t� confirm�e.
     * Lorsque <code>$confirm</code> est � <code>false</code>, une demande de 
     * confirmation est demand�e � l'utilisateur. Lorsque <code>confirm </code>
     * est � <code>true</code>, la t�che est cr��e.
     * 
     * @throws Exception si aucune �quation de recherche n'a �t� indiqu�e ou si
     * aucune notice ne correspond � cette �quation.
     */
    public function actionIndex($_equation, $confirm=false)
    {
        // Lance la recherche dans la base
        $this->selection=$this->search($_equation, array('max'=>-1));
        
        // Demande confirmation � l'utilisateur
        if (!$confirm)
        {
            Template::run
            (
                config::get('template'),
                array('equation'=>$_equation)
            );
            return;
        }

        // Cr�e une t�che au sein du gestionnaire de t�ches
        $id=Task::create()
            ->setRequest($this->request->setAction('Dedup')->keepOnly('_equation'))
            ->setTime(0)
            ->setLabel('Recherche de doublons pour ' . $this->selection->count() . ' notices de la base '.Config::get('database') . ' (equation : ' . $_equation . ')')
            ->setStatus(Task::Waiting)
            ->save()
            ->getId();
            
        Runtime::redirect('/TaskManager/TaskStatus?id='.$id);
    }
    
    /**
     * Ex�cute le d�doublonnage des notices 
     *
     * @param string $_equation
     */
    public function actionDedup($_equation)
    {
        // Lance la recherche dans la base
        $this->selection=$this->search($_equation, array('max'=>-1, 'sort'=>'+'));
        
        echo '<h1>Recherche de doublons pour l\'�quation ', $_equation, ' : ', $this->selection->count(), ' notice(s) � �tudier</h1>';
        
        $tests=Config::get('tests');
        
        $this->dumpTests($tests);
        
        echo '<hr />';
        
        // Lance les tests
        $this->runTests($tests, Config::get('format1'), Config::get('format2'));
    }

    /**
     * Ex�cute le d�doublonnage lors de la saisie d'une notice
     */
    public function actionDedupData()
    {
        // On nous a pass� des donn�es, cr�e une "fausse" s�lection avec
        // les param�tres de la requ�te
        $this->selection=array($this->request->getParameters());

        // R�cup�re les tests � r�aliser
        $tests=Config::get('tests');
        
        // Lance les tests
        $this->runTests($tests, null, Config::get('format'));
    }
    
    /**
     * Affiche une description "en clair" des tests qui seront appliqu�s
     * aux notices
     */
    private function dumpTests(array $tests)
    {
        echo "Pour chacune des notices s�lectionn�es, le programme va rechercher dans la base :<br />";

        echo "<ul>";
        foreach($tests as $num=>$test)
        {
            echo '<li>';
            
            $cond=array(); // conditions
            $t=array(); // tests � faire
            foreach ($test as $field=>$options)
            {
                if (is_scalar($options))
                {
                    $cond[$field]=$options;
                }
                else
                {
                    $t[$field]=$options;
                    $cond[$field]=null;
                }
            }
            
            if ($t)
            {
                $first=true;
                echo 'des notices avec ';
                foreach($t as $field=>$options)
                {
                    if (!$first) echo ' et ';
                    if (is_null($options))
                    {
                        echo 'un champ ', $field, ' identique    ';
                    }
                    else
                    {
                        $min=(int)Utils::get($options['min'], '100%');
                        if ($min===100)
                            echo 'un champ ', $field, ' identique';
                        else
                            echo 'un champ ', $field, ' similaire � ', $min, '%';
                    }
                    $first=false;
                        
                }
            }
            if ($cond)
            {
                $first=true;
                echo ' (si ';
                foreach($cond as $field=>$options)
                {
                    if (!$first) echo ' et ';
                    if (is_null($options))
                        echo $field, ' est renseign�';
                    else
                        echo $field, '=', $options;
                    $first=false;
                }
                //echo '';
            }
            echo ') ;';
        }
        echo "</ul>";
    }

    
    /**
     * Ex�cute tous les tests pass�s en param�tre sur les notices pr�sentes
     * dans la s�lection en cours
     *
     * @param Array $tests les tests � ex�cuter
     * @param String $format le format d'affichage des notices (le source
     * d'un template)
     */
    private function runTests(array $tests, $format1, $format2)
    {
        // format1 peut �tre null dans le cas de la recherche de doublons
        // � partir des donn�es pass�es en param�tre
        if (! is_null($format1))
            $path1=__FILE__.'/'.md5($format1).'.html';
        $path2=__FILE__.'/'.md5($format2).'.html';
        
        $duplicates=$this->search(null);
        
        // Nombre de notices ou nombre de tableau de donn�es pass�es en param�tre
        $nbRecord=is_array($this->selection) ? count($this->selection) : $this->selection->count();
        
        echo "\n", '<ol>', "\n";
        foreach($this->selection as $rank=>$record)
        {
            // Si les donn�es ont �t� pass�es en param�tre, $this->selection
            // devient le tableau des param�tres (ParamName => ParamValue)
            if (is_array($this->selection))
                $this->selection=$this->selection[$rank];
                
            // Dump la notice en cours
            $equation=$this->createEquation($tests);
            
            if ($equation==='')
            {
                if (! is_null($format1))
                {
                    echo '<li>';
                    Template::runSource($path1, $format1, $record);
                    echo '<p style="color: red">WARNING : impossible de d�doublonner cette notice, aucun test ne s\'applique<p>';
                    echo '</li>';
                }
                else
                {
                    echo '<p style="color: red">Impossible de d�doublonner cette notice, aucun test ne s\'applique<p>';
                }
                continue;
            }

            if (debug) echo 'Equation g�n�r�e : <a href="',Routing::linkFor('/Base/Search?_defaultop=or&_equation='.urlencode($equation)), '">', $equation, '</a><br />';

            if ($duplicates->search($equation, array('sort'=>'%', 'max'=>50)))
            {
                if (debug) echo $duplicates->searchInfo('internalquery'), '<br />';
                if (debug) echo $duplicates->count(), ' r�ponses<br />';

                $first=true;
                $hasDuplicates=false;
                foreach($duplicates as $duplicate)
                {
                    if (debug) echo '----------------------<br />';
                    $score=0;
                    if ($this->isDuplicate($tests, $duplicate, $score))
                    {
                        // Premier doublon trouv� : affiche la notice �tudi�e.
                        // Si c'est une recherche de doublon � partir de donn�es
                        // pass�es en param�tre (saisie), n'affiche rien
                        if ($first) 
                        {
                            if (! is_null($format1))
                            {
                                echo '<li>';
                                Template::runSource($path1, $format1, $record);
                                echo "\n", '    <ul>', "\n";
                            }
                            $hasDuplicates=true;
                            $first=false;
                        }
                        echo '        <li>';
                        echo $duplicates->searchInfo('score'), '% - ';
                        Template::runSource($path2, $format2, array('REFMAIN'=>$record['REF']), $duplicate);
                        echo '</li>', "\n";
                    }
                }
                if ($hasDuplicates && ! is_null($format1))
                {
                    echo '    </ul>', "\n";
                    echo '</li>', "\n";
                }
            }
            else
            {
                // Message affich� si on est dans le cas de donn�es pass�es en param�tre (format1 est null)
                if (is_null($format1))
                    echo '<p>Aucun doublon trouv�</p>';
            }
            
            TaskManager::progress($rank, $nbRecord);
        }
        
        echo '</ol>', "\n";
    }

    
    /**
     * Cr�e une �quation de recherche permettant de rechercher les doublons 
     * potentiels pour la notice en cours � partir des tests pass�s en 
     * param�tre.
     * 
     * La fonction examine tous les tests indiqu�s et va cr�er une �quation de 
     * recherche pour ceux qui peuvent �tre appliqu�s � la notice en cours.
     * 
     * Les �quations provenant de chacun des tests retenus sont combin�es en
     * OU pour former l'�quation finale � ex�cuter.
     * 
     * @return string|null l'�quation finale obtenue ou null si aucun des tests
     * indiqu�s ne s'applique � la notice en cours.
     */
    private function createEquation($tests)
    {
        $compare=array();
        if (debug) echo '<br /><br />';
        $equations=array();
        foreach($tests as $numTest=>$test)
        {
            $equation=array();
            foreach ($test as $field=>$options)
            {
                // Le champ indique une valeur (par exemple <type>ouvrage</type>)
                if (is_scalar($options))
                {
                    // Le test ne s'applique que si la notice en cours a la valeur indiqu�e
                    if (! $this->fieldContains($field, $options))
                    { 
                        if (debug) echo 'Test ', $numTest+1, ' ignor� (', $field, ' ne contient pas ', $options, ')<br />';
                        continue 2;
                    }

                    // tester dans notice �tudi�e si field=options
                    $method='DedupTokens';
                    $method=new $method();
                    
                    $equation[] = $field.'='.$method->getEquation($options);//dmdm;

                }
                
                // Le champ indique des crit�res (type, min...)
                else
                {
                    // R�cup�re la valeur du champ
                    $value=isset($this->selection[$field]) ? $this->selection[$field] : null; 

                    if (is_null($value) || $value==='')
                    { 
                        if (debug) echo 'Test ', $numTest+1, ' ignor� (', $field, ' non renseign�)<br />';
                        continue 2;
                    }
                    
                    $method='Dedup' . Utils::get($options['compare'], 'tokens');
                    $method=new $method();
                
                    $value=$method->getEquation($value);

                    $equation[]=$field . ':' . $value;
                }
            }
            
            if (debug) echo 'Test ', $numTest+1, ' applicable<br />';
            $equations[]=implode(' AND ', $equation);
        }
        
        if (debug) echo '<br />';
        
        // R�sultat
        
        // Construit la partie "sauf" de l'�quation si on dispose d'un num�ro de r�f�rence
        $REF=isset($this->selection['REF']) ? $this->selection['REF'] : null;
        $REF=($REF!=='0' && ! is_null($REF)) ? " -REF:$REF" : '';
        
        // Combine toutes les �quations
        $equations=implode(' OR ', $equations);
        
        // Retourne le r�sultat
        return $equations!=='' ? '(' . $equations. ')'. $REF : '';
    }

    /**
     * Teste si les deux notices pass�es en param�tre sont effectivement des
     * doublons en utilisant les tests indiqu�s
     *
     * @param array $tests
     * @param DatabaseRecord $selection
     * @param DatabaseRecord $duplicate
     * @return bool
     */
    private function isDuplicate(array $tests, DatabaseRecord $duplicate)
    {
        foreach ($tests as $numTest=>$test)
        {
            if (debug) echo '<br />test ', $numTest+1, ' :<br />';
            foreach($test as $field=>$options)
            {
                // R�cup�re la valeur du champ
                $value=isset($this->selection[$field]) ? $this->selection[$field] : null;             
                
                // cas d'une valeur
                if (is_scalar($options))
                {
                    if (! $this->fieldContains($field, $options)) 
                    {
                        if (debug) echo 'On n\'a pas ', $field, '=', var_export($options,true), ' dans le doublon, passage au test suivant<br />';
                        continue 2;
                    }
                    if (debug) echo 'On a bien ', $field, '=', var_export($options,true), ' dans le doublon<br />';
                    continue;   
                }
                
                if (is_null($options))
                {
                    if (is_null($value) || $value==='') 
                    {
                        if (debug) echo $field, '=', var_export($options,true), ' dans le doublon, non rempli, passage au test suivant<br />';
                        continue 2;
                    }
                }

                $min=(int)Utils::get($options['min'], '100%');

                $method='Dedup' . ucfirst(Utils::get($options['compare'], 'tokens'));
                if (debug) echo 'comparaison des tokens de ', $field, ' avec la m�thode ', $method, '<br />';
                $method=new $method();
                
                $score=$method->compare($value, $duplicate[$field]);
                if ($score < $min)
                {
                    if (debug) echo 'score inf�rieur � min=', $min, ', passage au test suivant<br />';
                    continue 2;
                }
                if (debug) echo 'score &gt;= � min=', $min, ', on continue<br />';
            }
            if (debug) echo 'tous les tests ont r�ussis<br />';
            return true;
        }
        return false;
    }
    
    /**
     * Teste si le champ indiqu� contient la valeur recherch�e.
     * 
     * Si le champ est multivalu� (champ articles), le test r�ussi si l'un
     * des articles correspond � la valeur recherch�e, sinon, le test r�ussi
     * si le champ contient la valeur recherch�e.
     * 
     * La comparaison ne tient pas compte de la casse des caract�res ni des
     * accents.
     * 
     * @param string $field le nom du champ dans lequel il faut rechercher
     * @param string|null $value la valeur recherch�e
     * @return bool true si le champ contient la valeur recherch�e.
     */
    private function fieldContains($field, $value)
    {
        // R�cup�re la valeur du champ
        $field=isset($this->selection[$field]) ? $this->selection[$field] : null;
        
        // Convertit la valeur recherch�e en minuscules et supprime les accents
        $value=trim(Utils::convertString($value, 'lower'));
        
        // Si le champ est vide, value doit �tre vide aussi 
        if (is_null($field))
        {
            return is_null($value) || $value==='';
        }

        // Explose le champ � l'aide du s�parateur d�fini en config
        // TODO : probl�me car le sep '/' est un caract�re commun qui peut se trouver dans des champs texte
        $sep=Config::get('sep');
        if (isset($sep))
            $field=explode($sep,$field);
        
        // Si le champ est monovalu�, on compare directement les valeurs
//        if (is_scalar($field))
//        {
//            return trim(Utils::convertString($field, 'lower')) === $value;
//        }
        
        // Si le champ est un champ articles, teste si l'un des articles correspond � la valeur recherch�e
        if (is_array($field))
        {
            foreach($field as $v)
            {
                if (trim(Utils::convertString($v, 'lower')) === $value) return true;
            }
            return false;
        }

        // Autre chose ?
        throw new Exception('non g�r� '.var_export(string,true));
        
    }
    
    /**
     * Supprime de la valeur pass�e ne param�tre value tout ce qui peut �tre 
     * g�nant dans une �quation de recherche (op�rateurs bool�ens, + et -, 
     * guillemets, crochets, parenth�ses...)
     *
     * @param unknown_type $value
     * @return unknown
     */
    private function cleanupValue($value)
    {
        if (is_scalar($value))
        {
            $value=preg_replace('~\b(?:et|ou|sauf|and|or|not|but)\b~i', '', $value);
            //$value=strtr($value, '"[]()+-:.', '         ');
            $value=implode(' ', Utils::tokenize($value));
            return $value;
        }
        
        foreach($value as &$v)
        {
            $v=$this->cleanupValue($v);
        }
        return $value;
    }
    
    protected function search($equation=null, $options=null)
    {
        // Le fichier de config du module indique la base � utiliser
        $database=Config::get('database');

        if (is_null($database))
            throw new Exception('La base de donn�es � utiliser n\'a pas �t� indiqu�e dans le fichier de configuration du module');

        $selection=Database::open($database);
        if (! is_null($equation))
        {
            if (! $selection->search($equation, $options))
                throw new Exception("Aucune r�ponse pour l'�quation $equation");
        }
        return $selection;
    }
    
    public function actionEdit(array $REF)
    {
        if (count($REF) !== 2)
            throw new InvalidArgumentException('Vous devez indiquer deux num�ros de notices valides.');
            
        Template::run
        (
            'edit.html', 
            array('REF'=>$REF)
        );
    }
}


?>