<?php
/**
 * @package     fab
 * @subpackage  database
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: XapianDatabase.php 1252 2011-04-14 11:25:50Z daniel.menard.bdsp $
 */

/**
 * Un driver de base de donn�es pour fab utilisant une base Xapian pour
 * l'indexation et le stockage des donn�es
 *
 * @package     fab
 * @subpackage  database
 */
class XapianDatabaseDriver extends Database
{
    /**
     * Le sch�ma de la base de donn�es (cf {@link getSchema()}).
     *
     * @var DatabaseSchema
     */
    private $schema=null;


    /**
     * Tableau interne indiquant, pour chaque champ de type 'AutoNumber' le nom
     * de la cl� metadata utilis�e pour stocker le dernier num�ro utilis�.
     *
     * Les cl�s du tableau sont les noms (minu sans accents) des champs de type
     * AutoNumber. La valeur est une chaine de la forme 'fab_autonumber_ID' ou
     * 'ID' est l'identifiant du champ.
     *
     * Ce tableau est initialis� dans InitDatabase() et n'est utilis� que par
     * saveRecord()
     *
     * @var {array(string)}
     */
    private $autoNumberFields=array();


    /**
     * Permet un acc�s � la valeur d'un champ dont on conna�t l'id
     *
     * Pour chaque champ (name,id), fieldById[id] contient une r�f�rence
     * vers fields[name] (ie modifier fields[i] ou fieldsById[i] changent la
     * m�me variable)
     *
     * @var Array
     */
    private $fieldById=array();


    /**
     * L'objet XapianDatabase retourn� par xapian apr�s ouverture ou cr�ation
     * de la base.
     *
     * @var XapianDatabase
     */
    private $xapianDatabase=null;


    /**
     * L'objet XapianDocument contenant les donn�es de l'enregistrement en
     * cours ou null s'il n'y a pas d'enregistrement courant.
     *
     * @var XapianDocument
     */
    private $xapianDocument=null;


    /**
     * Un flag indiquant si on est en train de modifier l'enregistrement en
     * cours ou non  :
     *
     * - 0 : l'enregistrement courant n'est pas en cours d'�dition
     *
     * - 1 : un nouvel enregistrement est en cours de cr�ation
     * ({@link addRecord()} a �t� appell�e)
     *
     * - 2 : l'enregistrement courant est en cours de modification
     * ({@link editRecord()} a �t� appell�e)
     *
     * @var int
     */
    private $editMode=0;


    /**
     * Un tableau contenant la valeur de chacun des champs de l'enregistrement
     * en cours.
     *
     * Ce tableau est pass� � l'objet {@link XapianDatabaseRecord} que l'on cr�e
     * lors de l'ouverture de la base.
     *
     * @var Array
     */
    private $fields=array();


    /**
     * L'objet XapianEnquire repr�sentant l'environnement de recherche.
     *
     * Vaut null tant que {@link search()} n'a pas �t� appell�e.
     *
     * @var XapianEnquire
     */
    private $xapianEnquire=null;


    /**
     * L'objet XapianQueryParser utilis� pour analyser les �quations de recherche.
     *
     * Initialis� par {@link setupSearch()} et utilis� uniquement dans
     * {@link search()}
     *
     * @var XapianQueryParser
     */
    private $xapianQueryParser=null;

    /**
     * Un query parser utilis� de fa�on sp�ciale pour g�n�rer la version
     * corrig�e (orthographe) de la requ�te de l'utilisateur.
     *
     * @var XapianQueryParser
     */
    private $xapianSpellChecker=null;

    /**
     * L'objet XapianMultiValueSorter utilis� pour r�aliser les tris multivalu�s.
     *
     * Initialis� par {@link setSortOrder()}.
     *
     * @var XapianMultiValueSorter
     */
    private $xapianSorter=null;

    /**
     * L'objet XapianMSet contenant les r�sultats de la recherche.
     *
     * Vaut null tant que {@link search()} n'a pas �t� appell�e.
     *
     * @var XapianMSet
     */
    private $xapianMSet=null;

    /**
     * L'objet XapianMSetIterator permettant de parcourir les r�ponses obtenues
     *
     * Vaut null tant que {@link search()} n'a pas �t� appell�e.
     *
     * @var XapianMSetIterator
     */
    private $xapianMSetIterator=null;

    /**
     * L'objet XapianQuery contenant l'�quation de recherche indiqu�e par
     * l'utilisateur (sans les filtres �ventuels appliqu�s).
     *
     * Vaut null tant que {@link search()} n'a pas �t� appell�e.
     *
     * Utilis� par {@link getQueryTerms()} pour retourner la liste des termes
     * composant la requ�te
     *
     * @var XapianQuery
     */
    private $xapianQuery=null;

    /**
     * L'objet XapianFilter contient la requ�te correspondant aux filtres
     * appliqu�s � la recherche
     *
     * Vaut null tant que {@link search()} n'a pas �t� appell�e.
     * Vaut null si aucun filtre n'a �t� sp�cifi�.
     *
     * @var XapianQuery
     */
    //private $xapianFilter=null;


    /**
     * L'objet UserQuery contient tous les param�trs de la requ�te ex�cut�e.
     *
     * Vaut null tant que {@link search()} n'a pas �t� appell�e.
     *
     * @var UserQuery
     */
    private $query=null;

    /**
     * Libell� de l'ordre de tri utilis� lors de la recherche.
     *
     * Si plusieurs crit�res de tri ont �t� indiqu�s lors de la requ�te,
     * le libell� obtenu est une chaine listant toutes les cl�s (s�par�es
     * par des espaces).
     *
     * Exemples :
     * - 'type', 'date-', '%', '+', '-' pour une cl� de tri unique
     * - 'type date-', 'date- %' pour une cl� de tri composite
     *
     * @var string
     */
    private $sortOrder='';

    /**
     * Tableau contenant les num�ros des slots qui contiennent les valeurs
     * composant l'ordre de tri en cours.
     *
     * @var null|array
     */
    private $sortKey=array();

    /**
     * Une estimation du nombre de r�ponses obtenues pour la recherche en cours.
     *
     * @var int
     */
    private $count=0;

    /**
     * La version corrig�e par le correcteur orthographique de xapian de la
     * requ�te en cours.
     *
     * @var string
     */
    private $correctedEquation=null;

    /**
     * MatchingSpy employ� pour cr�er les facettes de la recherche
     *
     * Exp�rimental (branche MatchSpy de Xapian), cf search().
     *
     * @var XapianMatchDecider
     */
    private $spy=null;


    /**
     * Le contexte de la derni�re recherche ex�cut�e (les options pass�es �
     * {@link search()}).
     *
     * @var Request // todo: utiliser classe Parameters quand dispo
     */
    private $options=null;

    /**
     * Retourne le sch�ma de la base de donn�es
     *
     * @param bool $raw Par d�faut, le sch�ma retourn� contient des propri�t�s _stopwords qui
     * contiennent un tableau permettant un acc�s direct aux mots-vides d�finis pour la base et
     * pour chacun des champs (cf initDatabase). Normallement, ces propri�t�s _stopwords ne
     * figurent pas dans le sch�ma (tel qu'il est stock� dans /data/schemas ou dans les
     * metadonn�es de la base).
     * Pour r�cup�rer le sch�ma r�el, appellez getSchema() en indiquant $raw=true.
     *
     * @return DatabaseSchema
     */
    public function getSchema($raw = false)
    {
        if ($raw)
            return unserialize($this->xapianDatabase->get_metadata('schema_object'));
        else
            return $this->schema;
    }

    // *************************************************************************
    // ***************** Cr�ation et ouverture de la base **********************
    // *************************************************************************

    /**
     * Cr�e une nouvelle base xapian
     *
     * @param string $path le path de la base � cr�er
     * @param DatabaseSchema $schema le sch�ma de la base � cr�er
     * @param array $options options �ventuelle, non utilis�
     */
    protected function doCreate($path, /* DS DatabaseSchema */ $schema, $options=null)
    {
        /* DS A ENLEVER */
        // V�rifie que le sch�ma de la base de donn�es est correcte
        if (true !== $t=$schema->validate())
            throw new Exception('Le sch�ma pass� en param�tre contient des erreurs : ' . implode('<br />', $t));

        // Compile le sch�ma
        $schema->compile();

        // Cr�e la base xapian
        $this->xapianDatabase=new XapianWritableDatabase($path, Xapian::DB_CREATE_OR_OVERWRITE); // todo: remettre � DB_CREATE
//        $this->xapianDatabase=Xapian::chert_open($path,Xapian::DB_CREATE_OR_OVERWRITE,8192);

        // Enregistre le schema dans la base
        $this->xapianDatabase->set_metadata('schema', $schema->toXml());
        $this->xapianDatabase->set_metadata('schema_object', serialize($schema));

        // Initialise les propri�t�s de l'objet
        $this->schema=$schema;
        $this->initDatabase(true);
    }

    /**
     * Modifie la structure d'une base de donn�es en lui appliquant le
     * sch�ma pass� en param�tre.
     *
     * La fonction se contente d'enregistrer le nouveau sch�ma dans
     * la base : selon les modifications apport�es, il peut �tre n�cessaire
     * ensuite de lancer une r�indexation compl�te (par exemple pour cr�er les
     * nouveaux index ou pour purger les champs qui ont �t� supprim�s).
     *
     * @param DatabaseSchema $newSchema le nouveau sch�ma de la base.
     */
    public function setSchema(DatabaseSchema $schema)
    {
        if (! $this->xapianDatabase instanceOf XapianWritableDatabase)
            throw new LogicException('Impossible de modifier le sch�ma d\'une base ouverte en lecture seule.');

        // V�rifie que le sch�ma de la base de donn�es est correct
        if (true !== $t=$schema->validate())
            throw new Exception('Le sch�ma pass� en param�tre contient des erreurs : ' . implode('<br />', $t));

        // Compile le sch�ma
        $schema->compile();

        // Enregistre le sch�ma dans la base
        $this->xapianDatabase->set_metadata('schema', $schema->toXml());
        $this->xapianDatabase->set_metadata('schema_object', serialize($schema));

        // Initialise les propri�t�s de l'objet
        $this->schema=$schema;
        $this->initDatabase(true);
    }


    /**
     * Ouvre une base Xapian
     *
     * @param string $path le path de la base � ouvrir.
     * @param bool $readOnly true pour ouvrir la base en lecture seule, false
     * pour l'ouvrir en mode lexture/�criture.
     */
    protected function doOpen($path, $readOnly=true)
    {
        // Ouverture de la base xapian en lecture
        if ($readOnly)
        {
            $this->xapianDatabase=new XapianDatabase($path);
        }

        // Ouverture de la base xapian en �criture
        else
        {
            $starttime=microtime(true);
            $maxtries=100;
            for($i=1; ; $i++)
            {
                try
                {
//                    echo "tentative d'ouverture de la base...<br />\n";
                    $this->xapianDatabase=new XapianWritableDatabase($path, Xapian::DB_OPEN);
                }
                catch (Exception $e)
                {
                    // comme l'exception DatabaseLockError de xapian n'est pas mapp�e en php
                    // on teste le d�but du message d'erreur pour d�terminer le type de l'exception
                    if (strpos($e->getMessage(), 'DatabaseLockError:')===0)
                    {
//                        echo 'la base est verrouill�e, essais effectu�s : ', $i, "<br />\n";

                        // Si on a fait plus de maxtries essais, on abandonne
                        if ($i>$maxtries) throw $e;

                        // Sinon, on attend un peu et on refait un essai
                        $wait=rand(1,9) * 10000;
//                        echo 'attente de ', $wait/10000, ' secondes<br />', "\n";
                        usleep($wait); // attend de 0.01 � 0.09 secondes
                        continue;
                    }

                    // Ce n'est pas une exception de type DatabaseLockError, on la propage
                    throw $e;
                }

                // on a r�ussi � ouvrir la base
                break;
            }
//            echo 'Base ouverte en �criture au bout de ', $i, ' essai(s). Temps total : ', (microtime(true)-$starttime), ' sec.<br />', "\n";
        }

        // Charge le sch�ma de la base
        $this->schema=unserialize($this->xapianDatabase->get_metadata('schema_object'));
        if (! $this->schema instanceof DatabaseSchema)
            throw new Exception("Impossible d'ouvrir la base, sch�ma non g�r�");

        // Initialise les propri�t�s de l'objet
        $this->initDatabase($readOnly);
    }


    /**
     * Initialise les propri�t�s de la base
     *
     * @param bool $readOnly
     */
    private function initDatabase($readOnly=true)
    {
        // Cr�e le tableau qui contiendra la valeur des champs
        $this->fields=array_fill_keys(array_keys($this->schema->fields), null);

        // Cr�e l'objet DatabaseRecord
        $this->record=new XapianDatabaseRecord($this->fields, $this->schema);

        foreach($this->schema->fields as $name=>$field)
            $this->fieldById[$field->_id]=& $this->fields[$name];

        foreach($this->schema->indices as $name=>&$index) // fixme:
            $this->indexById[$index->_id]=& $index;

        foreach($this->schema->lookuptables as $name=>&$lookuptable) // fixme:
            $this->lookuptableById[$lookuptable->_id]=& $lookuptable;

        // Les propri�t�s qui suivent ne sont initialis�es que pour une base en lecture/�criture
//        if ($readOnly) return;

        // Mots vides de la base
        $this->schema->_stopwords=array_flip(Utils::tokenize($this->schema->stopwords));

        // Cr�e la liste des champs de type AutoNumber + mots-vides des champs
        foreach($this->schema->fields as $name=>$field)
        {
            // Champs autonumber
            if ($field->_type === DatabaseSchema::FIELD_AUTONUMBER)
                $this->autoNumberFields[$name]='fab_autonumber_'.$field->_id;

            // Mots vides du champ
            if ($field->defaultstopwords)
            {
                if ($field->stopwords==='')
                    $field->_stopwords=$this->schema->_stopwords;
                else
                    $field->_stopwords=array_flip(Utils::tokenize($field->stopwords.' '.$this->schema->stopwords));
            }
            else
            {
                if ($field->stopwords==='')
                    $field->_stopwords=array();
                else
                    $field->_stopwords=array_flip(Utils::tokenize($field->stopwords));
            }
        }
    }


    // *************************************************************************
    // ********* Ajout/modification/suppression d'enregistrements **************
    // *************************************************************************


    /**
     * Initialise la cr�ation d'un nouvel enregistrement
     *
     * L'enregistrement ne sera effectivement cr�� que lorsque {@link update()}
     * sera appell�.
     *
     * @throws DatabaseReadOnlyException si la base est ouverte en lecture seule
     */
    public function addRecord()
    {
        // V�rifie que la base n'est pas en lecture seule
        if (! $this->xapianDatabase instanceof XapianWritableDatabase)
            throw new DatabaseReadOnlyException();

        // R�initialise tous les champs � leur valeur par d�faut
        foreach($this->fields as $name=>&$value)
            $value=null;

        // R�initialise le document xapian en cours
        $this->xapianDocument=new XapianDocument();

        // M�morise qu'on a une �dition en cours
        $this->editMode=1;
    }


    /**
     * Initialise la modification d'un enregistrement existant.
     *
     * L'enregistrement  ne sera effectivement modifi� que lorsque {@link update}
     * sera appell�.
     *
     * @throws DatabaseReadOnlyException si la base est ouverte en lecture seule
     * @throws DatabaseNoRecordException s'il n'y a pas d'enregistrement courant
     */
    public function editRecord()
    {
        // V�rifie que la base n'est pas en lecture seule
        if (! $this->xapianDatabase instanceof XapianWritableDatabase)
            throw new DatabaseReadOnlyException();

        // V�rifie qu'on a un enregistrement courant
        if (is_null($this->xapianDocument))
            throw new DatabaseNoRecordException();

        // M�morise qu'on a une �dition en cours
        $this->editMode=2;
    }


    /**
     * Sauvegarde l'enregistrement en cours.
     *
     * @throws DatabaseNotEditingException si l'enregistrement courant n'est pas
     * en cours de modification, c'est-�-dire si on appelle saveRecord() sans
     * avoir appell� {@link addRecord()} ou {@link editRecord()} auparavant.
     *
     * @return int l'identifiant (docid) de l'enregistrement cr�� ou modifi�
     */
    public function saveRecord()
    {
        // V�rifie qu'on a une �dition en cours
        if ($this->editMode === 0)
            throw new DatabaseNotEditingException();

        // Affecte une valeur aux champs AutoNumber qui n'en n'ont pas
        foreach($this->autoNumberFields as $name=>$key)
        {
            // Si le champ autonumber n'a pas de valeur, on lui en donne une
            if (! $this->fields[$name]) // null ou 0 ou '' ou false
            {
                // get_metadata retourne '' si la cl� n'existe pas. Valeur initiale=1+(int)''=1
                $value=1+(int)$this->xapianDatabase->get_metadata($key);
                $this->fields[$name]=$value;
                $this->xapianDatabase->set_metadata($key, $value);
            }

            // Sinon, si la valeur indiqu�e est sup�rieure au compteur, on met � jour le compteur
            else
            {
                $value=(int)$this->fields[$name];
                if ($value>(int)$this->xapianDatabase->get_metadata($key))
                    $this->xapianDatabase->set_metadata($key, $value);
            }
        }

        // Indexe l'enregistrement
        $this->initializeDocument();

        // Ajoute un nouveau document si on est en train de cr�er un enreg
        if ($this->editMode==1)
        {
            $docId=$this->xapianDatabase->add_document($this->xapianDocument);
        }

        // Remplace le document existant sinon
        else
        {
            $docId=$this->xapianMSetIterator->get_docid();
            $this->xapianDatabase->replace_document($docId, $this->xapianDocument);
        }

        // Edition termin�e
        $this->editMode=0;
//        pre($this->schema);
//        die('here');

        // Retourne le docid du document cr�� ou modifi�
        return $docId;
    }


    /**
     * Annule l'�dition de l'enregistrement en cours.
     *
     * @throws DatabaseNotEditingException si l'enregistrement courant n'est pas
     * en cours de modification, c'est-�-dire si on appelle saveRecord() sans
     * avoir appell� {@link addRecord()} ou {@link editRecord()} auparavant.
     */
    public function cancelUpdate()
    {
        // V�rifie qu'on a une �dition en cours
        if ($this->editMode == 0)
            throw new DatabaseNotEditingException();

        // Recharge le document original pour annuler les �ventuelles modifications apport�es
        $this->loadDocument();

        // Edition termin�e
        $this->editMode=0;
    }


    /**
     * Supprime l'enregistrement en cours
     *
     */
    public function deleteRecord()
    {
        // V�rifie que la base n'est pas en lecture seule
        if (! $this->xapianDatabase instanceof XapianWritableDatabase)
            throw new ReadOnlyDatabaseException();

        // Interdiction d'appeller deleteRecord() juste apr�s addRecord()
        if ($this->editMode == 1)
            throw new LogicException("Appel de deleteRecord() apr�s un appel � addRecord()");

        // Supprime l'enregistrement
        $docId=$this->xapianMSetIterator->get_docid();
        $this->xapianDatabase->delete_document($docId);
    }


    // *************************************************************************
    // *************************** Indexation **********************************
    // *************************************************************************


    /**
     * Retourne un extrait de chaine d�limit�s par des positions ou des chaines
     * de d�but et de fin.
     *
     * Start et End repr�sente les positions de d�but et de fin de la chaine �
     * obtenir. Chacun des deux peut �tre soit un entier soit une chaine.
     * Entier positif = position � partir du d�but
     * Entier n�gatif = position depuis la fin
     *
     * @todo compl�ter la doc
     *
     * @param string $value
     * @param int|string $start
     * @param int|string $end
     * @return string
     */
    private function startEnd($value, $start, $end=null)
    {
        if (is_int($start) && is_int($end) && (($start>0 && $end>0) || ($start<0 && $end<0)) && ($start > $end))
            throw new InvalidArgumentException('Si start et end sont des entiers de m�me signe, start doit �tre inf�rieur � end');

        // On ignore les espaces de d�but : si on a "    AAAAMMJJ", (0,3) doit retourner AAAA, pas les espaces
        $value=ltrim($value);

        if (is_int($start))
        {
            if ($start) // 0 = prendre tout
            {
                // start > 0 : on veut � partir du i�me caract�re, -1 pour php
                if ($start > 0)
                {
                    if (is_int($end) && $end>0 ) $end -= $start-1;
                    if (false === $value=substr($value, $start-1)) return '';
                }

                // start < 0 : on veut les i derniers caract�res
                elseif (strlen($value)>-$start)
                    $value=substr($value, $start);
            }
        }
        elseif($start !=='')
        {
            $pt=stripos($value, $start); // insensible � la casse mais pas aux accents
            if ($pt !== false)
                $value=substr($value, $pt+strlen($start));
        }

        if (is_int($end))
        {
            if ($end) // 0 = prendre tout
            {
                if ($end>0)
                    $value=substr($value, 0, $end);
                else
                    $value=substr($value, 0, $end);
            }
        }
        elseif($end !=='')
        {
            $pt=stripos($value, $end);
            if ($pt !== false)
                $value=substr($value, 0, $pt);
        }

        return trim($value);
    }


    /**
     * Ajoute un terme dans l'index
     *
     * @param string $term le terme � ajout�
     * @param string $prefix le pr�fixe � ajouter au terme
     * @param int $weight le poids du terme
     * @param null|int $position null : le terme est ajout� sans position,
     * int : le terme est ajout� avec la position indiqu�e
     */
    private function addTerm($term, $prefix, $weight=1, $position=null)
    {
        if (is_null($position))
        {
            $this->xapianDocument->add_term($prefix.$term, $weight);
        }
        else
        {
            $this->xapianDocument->add_posting($prefix.$term, $position, $weight);
        }
    }


    /**
     * Initialise le document xapian en cours lors de la cr�ation ou de la
     * modification d'un enregistrement.
     *
     */
    private function initializeDocument()
    {
        // On ne stocke dans doc.data que les champs non null
        $data = array();
        foreach($this->fieldById as $id=>$value)
            if (count($value)) $data[$id]=$value; // Supprime les null et array()

        // Dans une version pr�c�dente, on utilisait array_filter($this->fieldById, 'count')
        // qui fait la m�me chose que le code ci-dessus. Mais depuis php 5.2.11, cela ne
        // fonctionne plus car fieldById est une r�f�rence qui se retrouve cass�e si on la passe
        // � array_filter. Cf http://bugs.php.net/bug.php?id=51986 pour plus d'informations.

        // Stocke les donn�es de l'enregistrement
        $this->xapianDocument->set_data(serialize($data));

        // Supprime tous les tokens existants
        $this->xapianDocument->clear_terms();

        // Met � jour chacun des index
        $position=0;
        foreach ($this->schema->indices as $index)
        {
            // D�termine le pr�fixe � utiliser pour cet index
            $prefix=$index->_id.':';

            // Pour chaque champ de l'index, on ajoute les tokens du champ dans l'index
            foreach ($index->fields as $name=>$field)
            {
                // Traite tous les champs comme des champs articles
                $data=(array) $this->fields[$name];

                // Initialise la liste des mots-vides � utiliser
                $stopwords=$this->schema->fields[$name]->_stopwords;

                // Index chaque article
                $count=0;
                foreach($data as $value)    // fixme: seulement si indexation au mot !!!
                {
                    // start et end
                    if ($value==='') continue;
                    if ($field->start || $field->end)
                        if ('' === $value=$this->startEnd($value, $field->start, $field->end)) continue;

                    // Compte le nombre de valeurs non nulles
                    ++$count;

                    // Si le champ n'est pas index� au mot ou � l'article, on a fini
                    if (! $field->words && ! $field->values) continue;

                    // Tokenise le champ
                    $tokens=Utils::tokenize($value);

                    // Indexation au mot et � la phrase
                    if ($field->words)
                    {
                        foreach($tokens as $term)
                        {
                            // V�rifie que la longueur du terme est dans les limites autoris�es
                            if (strlen($term)<self::MIN_TERM or strlen($term)>self::MAX_TERM) continue;

                            // Si c'est un mot vide et que l'option "indexStopWords" est � false, on ignore le terme
                            if (! $this->schema->indexstopwords && isset($stopwords[$term])) continue;

                            // Ajoute le terme dans le document
                            $this->addTerm($term, $prefix, $field->weight, $field->phrases?$position:null);

                            // Correcteur orthographique
                            if (isset($index->spelling) && $index->spelling)
                                $this->xapianDatabase->add_spelling($term);

                            // Incr�mente la position du terme en cours
                            $position++;
                        }
                    }

                    // Indexation � l'article
                    if ($field->values)
                    {
                        $term=implode('_', $tokens);
                        if (strlen($term)>self::MAX_TERM-2)
                            $term=substr($term, 0, self::MAX_TERM-2);
                        $term = strtr($term, '@', 'a');         // Il ne faut pas avoir d'arobase dans les articles (d�clenche une recherche � la phrase dans Xapian)
                        $term = '_' . $term . '_';
                        $this->addTerm($term, $prefix, $field->weight, null);
                    }

                    // Fait de la "place" entre chaque article
                    $position+=100;
                    $position-=$position % 100;
                }

                // Indexation de type "count"
                if ($field->count)
                    $this->addTerm($count ? '__has'.$count : '__empty', $prefix);
            }
        }

        // Tables de lookup
        foreach ($this->schema->lookuptables as $lookupTable)
        {
            // D�termine le pr�fixe � utiliser pour cette table
            $prefix='T'.$lookupTable->_id.':';

            // Parcourt tous les champs qui alimentent cette table
            foreach($lookupTable->fields as $name=>$field)
            {
                // Traite tous les champs comme des champs articles
                $data=(array) $this->fields[$name];
                $data=array_slice($data, $field->startvalue-1, $field->endvalue===0 ? null : ($field->endvalue));

                // Initialise la liste des mots-vides � utiliser
                $stopwords=$this->schema->fields[$name]->_stopwords;

                // Index chaque article
                $count=0;
                foreach($data as $value)
                {
                    // start et end
                    if ($value==='') continue;

                    if ($field->start || $field->end)
                        if ('' === $value=$this->startEnd($value, $field->start, $field->end)) continue;

                    // Si la valeur est trop longue, on l'ignore
                    if (strlen($value)>self::MAX_ENTRY) continue;

                    // Table de lookup de type simple
                    $this->addTerm($value, $prefix);
                }
            }
        }

        // Cl�s de tri
        // FIXME : faire un clear_value avant. Attention : peut vire autre chose que des cl�s de tri. � voir
        foreach($this->schema->sortkeys as $sortkeyname=>$sortkey)
        {
            foreach($sortkey->fields as $name=>$field)
            {
                // R�cup�re les donn�es du champ, le premier article si c'est un champ multivalu�
                $value=$this->fields[$name];
                if (is_array($value)) $value=reset($value);

                // start et end
                if ($field->start || $field->end)
                    $value=$this->startEnd($value, $field->start, $field->end);

                $value=implode(' ', Utils::tokenize($value));

                // Ne prend que les length premiers caract�res
                if ($field->length)
                {
                    if (strlen($value) > $field->length)
                        $value=substr($value, 0, $field->length);
                }

                // Si on a une valeur, termin�, sinon examine les champs suivants
                if ($value!==null && $value !== '') break;
            }

            if (!isset($sortkey->type)) $sortkey->type='string'; // FIXME: juste en attendant que les bases asco soient recr��es
            switch($sortkey->type)
            {
                case 'string':
                    if (is_null($value) || $value === '') $value=chr(255);
                    break;
                case 'number':
                    if (! is_numeric($value)) $value=INF;
                    $value=Xapian::sortable_serialise($value);
                    break;
                default:
                    throw new LogicException("Type de cl� incorrecte pour la cl� de tri $sortkeyname");

            }
            $this->xapianDocument->add_value($sortkey->_id, $value);
        }

    }


    // *************************************************************************
    // **************************** Recherche **********************************
    // *************************************************************************

    /**
     * Met en place l'environnement de recherche
     *
     * La fonction cr�e tous les objets xapian dont on a besoin pour faire
     * analyser une �quation et lancer une recherche
     */
    private function setupSearch()
    {
        // Initialise l'environnement de recherche
        $this->xapianEnquire=new XapianEnquire($this->xapianDatabase);

        // Initialise le QueryParser
        $this->xapianQueryParser=new XapianQueryParser();

        // Param�tre l'index par d�faut (l'index global)
        $defaultIndex=$this->options->defaultindex;
        if (! is_null($defaultIndex))
        {
            $default= Utils::convertString($defaultIndex, 'alphanum');
            if (isset($this->schema->indices[$default]))
            {
                $this->xapianQueryParser->add_prefix('', $this->schema->indices[$default]->_id.':');
            }
            elseif (isset($this->schema->aliases[$default]))
            {
                foreach($this->schema->aliases[$default]->indices as $index)
                    $this->xapianQueryParser->add_prefix('', $index->_id.':');
            }
            else
            {
                throw new Exception("Impossible d'utiliser '$defaultIndex' comme index global : ce n'est ni un index, ni un alias.");
            }
        }

        // Indique au QueryParser la liste des index de base
        foreach($this->schema->indices as $name=>$index)
            $this->xapianQueryParser->add_prefix($name, $index->_id.':');
/*
        foreach($this->schema->indices as $name=>$index)
        {
            if (!isset($index->_type)) $index->_type=DatabaseSchema::INDEX_PROBABILISTIC; // cas d'un sch�ma compil� avant que _type ne soit impl�ment�
            switch($index->_type)
            {
                case DatabaseSchema::INDEX_PROBABILISTIC:
                    $this->xapianQueryParser->add_prefix($name, $index->_id.':');
                    break;

                case DatabaseSchema::INDEX_BOOLEAN:
                    $this->xapianQueryParser->add_boolean_prefix($name, $index->_id.':');
                    break;

                default:
                    throw new Exception('index ' . $name . ' : type incorrect : ' . $index->_type);
            }
        }
*/
        // Indique au QueryParser la liste des alias
        foreach($this->schema->aliases as $aliasName=>$alias)
        {
            foreach($alias->indices as $index)
                $this->xapianQueryParser->add_prefix($aliasName, $index->_id.':');
        }
/*
        foreach($this->schema->aliases as $aliasName=>$alias)
        {
            if (!isset($alias->_type)) $alias->_type=DatabaseSchema::INDEX_PROBABILISTIC; // cas d'un sch�ma compil� avant que _type ne soit impl�ment�
            switch($alias->_type)
            {
                case DatabaseSchema::INDEX_PROBABILISTIC:
                    foreach($alias->indices as $name=>$index)
                        $this->xapianQueryParser->add_prefix($aliasName, $index->_id.':');
                    break;

                case DatabaseSchema::INDEX_BOOLEAN:
                    foreach($alias->indices as $name=>$index)
                        $this->xapianQueryParser->add_boolean_prefix($aliasName, $index->_id.':');
                    break;

                default:
                    throw new Exception('index ' . $name . ' : type incorrect : ' . $index->_type);
            }
        }
*/

        // Initialise le stopper (suppression des mots-vides)
        $this->stopper=new XapianSimpleStopper();
        foreach ($this->schema->_stopwords as $stopword=>$i)
            $this->stopper->add($stopword);
        $this->xapianQueryParser->set_stopper($this->stopper); // fixme : stopper ne doit pas �tre une variable locale, sinon segfault

        $this->xapianQueryParser->set_database($this->xapianDatabase); // indispensable pour FLAG_WILDCARD

        // Exp�rimental : autorise un value range sur le champ REF s'il existe une cl� de tri nomm�e REF
        foreach($this->schema->sortkeys as $name=>$sortkey)
        {
            if (!isset($sortkey->type)) $sortkey->type='string'; // FIXME: juste en attendant que les bases asco soient recr��es
            if ($sortkey->type==='string')
            {
                // todo: xapian ne supporte pas de pr�fixe pour les stringValueRangeProcessor
                // $this->vrp=new XapianStringValueRangeProcessor($this->schema->sortkeys['ref']->_id);
            }
            else
            {
                $this->vrp=new XapianNumberValueRangeProcessor($sortkey->_id, $name.':', true);
                $this->xapianQueryParser->add_valuerangeprocessor($this->vrp);
            }
            // todo: date
        }
    }

    /**
     * Fonction callback utilis�e par {@link parseQuery()} pour convertir
     * la syntaxe [xxx] utilis�e dans une �quation de recherche en recherche
     * � l'article
     *
     * @param array $matches le tableau g�n�r� par preg_replace_callback
     * @return string
     */
    private function searchByValueCallback($matches)
    {
        // r�cup�re le terme � convertir
        $term=trim($matches[1]);

        // Regarde si le terme se termine par une troncature
        $wildcard=substr($term, -1)==='*';

        // Concat�ne tous les tokens du terme avec un underscore
        $term=implode('_', Utils::tokenize($term));

        // Tronque l'article s'il d�passe la limite autoris�e
        if (strlen($term)>self::MAX_TERM-2)
            $term=substr($term, 0, self::MAX_TERM-2);

        // Il ne faut pas avoir d'arobase dans les articles (d�clenche une recherche � la phrase dans Xapian)
        $term=strtr($term, '@', 'a');

        // Encadre le terme avec des underscores et ajoute �ventuellement la troncature
        $term = '_' . $term ; // fixme: pb si ce qui pr�c�de est un caract�re aa[bb]cc -> aa_bb_cc. Faut g�rer ?
        if ($wildcard) $term.='*'; else $term.='_';

        // Termin�
        return $term;
    }

    /**
     * Traduit les op�rateurs bool�ens fran�ais (et, ou, sauf) en op�rateurs
     * reconnus par xapian.
     *
     * @param string $equation
     * @return string
     */
    private function protectOperators($equation)
    {
        if ($this->options->opanycase)
            $search = array('~\b(ET|AND)\b~i','~\b(OU|OR)\b~i','~\b(SAUF|BUT|NOT)\b~i');
        else
            $search = array('~\b(ET|AND)\b~','~\b(OU|OR)\b~','~\b(SAUF|BUT|NOT)\b~');

        $replace = array(':AND:', ':OR:', ':NOT:');

        $t=explode('"', $equation);
        foreach($t as $i=>&$h)
        {
            if ($i%2==1) continue;
            $h=preg_replace($search, $replace, $h);
        }
        return implode('"', $t);
    }

    private function restoreOperators($equation)
    {
        return str_replace
        (
            array(':and:', ':or:', ':not:'),
            array('AND', 'OR', 'NOT'),
            $equation
        );
    }

    /**
     * Construit une requ�te xapian � partir d'une �quation de recherche saisie
     * par l'utilisateur.
     *
     * Si la requ�te � analyser est null ou une chaine vide, un objet XapianQuery
     * special permettant de rechercher tous les documents pr�sents dans la base
     * est retourn�.
     *
     * @param string|array $equation �quation(s) � analyser.
     *
     * @param int $intraOpCode Op�rateur par d�faut � utiliser au sein de chaque
     * �quation.
     *
     * @param int $interOpCode Op�rateur � utiliser pour combiner ensemble les
     * diff�rentes �quations (lorsque $equation est un tableau d'�quations).
     *
     * @param string $index Par d�faut la requ�te est analys�e en utilisant
     * l'index par d�faut d�finit dans le QueryParser. On peut forcer
     * l'utilisation d'un autre index en indiquant son nom ici.
     *
     * @return XapianQuery
     */
    private function parseQuery($equation, $intraOpCode=XapianQuery::OP_OR, $interOpCode=XapianQuery::OP_OR, $index=null)
    {
        // Param�tre l'op�rateur par d�faut du Query Parser
        $this->xapianQueryParser->set_default_op($intraOpCode);

        // D�termine les flags du Query Parser
        $flags=
            XapianQueryParser::FLAG_BOOLEAN |
            XapianQueryParser::FLAG_PHRASE |
            XapianQueryParser::FLAG_LOVEHATE |
            XapianQueryParser::FLAG_WILDCARD |
            XapianQueryParser::FLAG_PURE_NOT;

        if ($this->options->opanycase)
            $flags |= XapianQueryParser::FLAG_BOOLEAN_ANY_CASE;

        $query=array();
        $nb=0;
        foreach((array)$equation as $equation)
        {
            $equation=trim($equation);
            if ($equation==='') continue;

            if ($equation==='*')
            {
                $query[$nb++] = new XapianQuery('');
                continue;
            }

            // Pr�-traitement de l'�quation pour que xapian l'interpr�te comme on souhaite
            $equation=preg_replace_callback('~(?:[a-z0-9]\.){2,9}~i', array('Utils', 'acronymToTerm'), $equation); // sigles � traiter, xapian ne le fait pas s'ils sont en minu (a.e.d.)
            $equation=preg_replace_callback('~\[(.*?)\]~', array($this,'searchByValueCallback'), $equation);
            $equation=strtr($equation, array('�'=>'ae', '�'=>'oe'));
            $equation=strtr // ticket 125. Index= test  n'est pas interpr�t� comme Index=Test
            (
                $equation,
                array
                (
                    ' : ' => ':', ' :'  => ':', ': '  => ':',
                    ' = ' => '=', ' ='  => '=', '= '  => '=' ,
                )
            );
            $equation=$this->protectOperators($equation);
            $equation=Utils::convertString($equation, 'queryparser'); // FIXME: utiliser la m�me table que tokenize()
            $equation=$this->restoreOperators($equation);

            if (!is_null($index))
                $equation="$index:($equation)";

            // Construit la requ�te
            $query[$nb++]=$this->xapianQueryParser->parse_Query(utf8_encode($equation), $flags);
        }

        if ($nb===0) return null;
        if ($nb===1) return $query[0];
        return new XapianQuery($interOpCode, $query);
    }

    /**
     * Indique si la requ�te xapian pass�e en param�tre est Xapian::MatchAll.
     *
     * @param XapianQuery $query
     * @return bool
     */
    private function isMatchAll(XapianQuery $query)
    {
        return $query->get_description()=='Xapian::Query(<alldocuments>)';
    }

    /**
     * Corrige l'orthographe de l'�quation de recherche en cours.
     *
     * La m�thode extrait de la requ�te en cours tous les termes qui n'existent
     * pas dans la base.
     *
     * Elle appelle ensuite pour chaque mot la m�thode
     * XapianDatabase::->get_spelling_suggestion().
     *
     * Si xapian propose une suggestion, le mot d'origine est remplac� par
     * celle-ci dans la requ�te en cours en utilisant le format pass� en
     * param�tre.
     *
     * Lors de ce remplacement, la m�thode essaie de donner � la suggestion
     * trouv�e la m�me casse de caract�res que le mot d'origine (si le mot �tait
     * en majuscules, la suggestion sera en majuscules, s'il �tait en minuscules
     * avec une initiale, la suggestion aura la m�me casse, etc.)
     *
     * Les sigle sont �galement g�r�s : ils sont transform�s en termes puis
     * r�introduits sous forme de sigles dans l'�quation d'origine.
     *
     * Remarque : les suggestions faites sont toujours non accentu�es.
     *
     * @param string $format le format (fa�on sprintf) � utiliser. Doit
     * obligatoirement contenir la chaine '%s'.
     */
    private function spellcheckEquation($format='<strong>%s</strong>')
    {
        // requ�te utilis�e pour les tests :
        // /debug.php/Base/Search?_equation=PZTIENT+Pztient+pztient+P.Z.T.I.E.N.T.+%5BEducation+Sznt%E9%5D+z++-priqe+en+chzrge+chzrge+%9Cuef+AND+pztient+dizb%E9tique+OR+diazbet*+REF%3A12+AutPhys%3A%28Flahzult+A.%29+%2BZ.N.A.E.S.+Titre%3Desai&_defaultop=OR
        timer && Timer::enter();

        // Cr�e la liste des termes de la requ�te qui n'existent pas dans la base
        // Chaque terme peut appara�tre dans plusieurs index (12:test, 15:test, etc.)
        // On consid�re qu'un terme n'existe pas s'il ne figure dans aucun des index
        timer && Timer::enter('Cr�ation de la liste des mots inexistants');
        $found = array();
        foreach($this->searchInfo('internalqueryterms') as $term)
        {
            // Extrait le pr�fixe du terme
            $prefix = '';
            if (false !== $pt=strpos($term, ':'))
            {
                $prefix = substr($term, 0, $pt+1);
                $term = substr($term, $pt+1);
            }

            // Extrait les mots pr�sents dans le terme (essentiellement pour les articles)
            $words = str_word_count($term, 1, '0123456789@');
//          $docCount = $this->xapianDatabase->get_doccount();
            foreach($words as $word)
            {
                // Si on a d�j� rencontr� ce mot, termin�
                if (isset($found[$word]) && $found[$word]) continue;

                // Si c'est un nombre, termin�
                if (ctype_digit($word)) continue;  // �vite un warning xapian : no overloaded function get_spelling_suggestion(int))

                if ($this->xapianDatabase->term_exists($prefix.$word))
//                if ($this->xapianDatabase->get_termfreq($prefix.$word) > 0.001*$docCount)
                    $found[$word] = true;
                elseif(! isset($found[$word]))
                    $found[$word] = false;
            }
        }
        timer && Timer::leave();

        // Recherche une suggestion pour chacun des mots obtenus
        timer && Timer::enter('get_spelling_suggestion');
        foreach($found as $word=>$exists)
        {
            if (! $exists && $correction = $this->xapianDatabase->get_spelling_suggestion($word, 2))
                $corrections[$word] = $correction;
        }
        timer && Timer::leave();

        timer && Timer::enter('Remplacement des mots par les corrections');
        // R�cup�re l'�quation de recherche de l'utilisateur
        $string = $this->searchInfo('equation');

        // Cr�e une version en minuscules non accentu�es de l'�quation de recherche
        $string = strtr($string, array('�'=>'ae', '�'=>'oe')); // ligatures
        $string = preg_replace_callback('~(?:[a-z0-9]\.){2,9}~i', array('Utils', 'acronymToTerm'), $string); // sigles
        $lower = Utils::convertString($string, 'alphanum');

        // Extrait les mots pr�sents dans l'�quation en minu en stockant leur position de d�part
        $words = str_word_count($lower, 2, '0123456789@_');

        // offset enregistre le d�calage des positions d�s aux remplacements d�j� effectu�s
        $offset = 0;

        // Corrige les mots
        foreach($words as $position=>$word)
        {
            if (isset($corrections[$word]))
            {
                // R�cup�re la correction
                $correction = $corrections[$word];

                // Essaie de donner � la suggestion la m�me "casse" que le mot d'origine
                $word=substr($string, $position + $offset, strlen($word));
                if (ctype_upper($word))
                    $correction=strtoupper($correction);
                elseif (ctype_upper($word[0]))
                    $correction=ucfirst($correction);

                $correction = sprintf($format, $correction);

                $string = substr_replace($string, $correction, $position + $offset, strlen($word));
                $offset += (strlen($correction) - strlen($word));
            }
        }

        $this->correctedEquation = $offset ? $string : '';
        timer && Timer::leave();

        timer && Timer::leave();
    }


    /**
     * Fonction exp�rimentale utilis�e par {@link parseQuery()} pour convertir
     * les num�ros de pr�fixe pr�sents dans l'�quation retourn�e par xapian en
     * noms d'index tels que d�finis par l'utilisateur.
     *
     * @param array $matches le tableau g�n�r� par preg_replace_callback
     * @return string
     */
    private function idToName($matches)
    {
        $id=(int)$matches[1];
        foreach($this->schema->indices as $index)
            if ($index->_id===$id) return '<span style="color:#00A;">'.$index->name.'</span>=';
        return $matches[1];
    }



    public function getFacet($table, $sortByCount=false)
    {
        if (! $this->spy) return array();

        $key=Utils::ConvertString($table, 'alphanum');
        if (!isset($this->schema->lookuptables[$key]))
            throw new Exception("La table de lookup '$table' n'existe pas");
        $prefix='T' . $this->schema->lookuptables[$key]->_id . ':';
        $facet=$this->spy->get_terms_as_array($prefix);

        // workaround bug dans TermSpy : si la lettre qui suit le prefix est une maju, l'entr�e est ignor�e
//        $t=array();
//        foreach($facet as $key=>&$value)
//            $t[substr($key,1)]=$value;
//        $facet=$t;
        // fin workaround

        if ($sortByCount)
            arsort($facet, SORT_NUMERIC);
        else
            ksort($facet, SORT_LOCALE_STRING);
        return $facet;
    }

    /**
     * Analyse une �quation de recherche utilis�e pour "booster" certains
     * documents.
     *
     * Xapian supporte l'op�rateur OP_SCALE_WEIGHT qui permet de pond�rer le
     * poids obtenu par une requ�te. Associ� � l'op�rateur OP_AND_MAYBE, c'est
     * un bon moyen de booster certains documents.
     *
     * Malheureusement, cet op�rateur ne peut pas �tre utilis�e dans une
     * requ�te analys�e par le query parser standard de xapian : la syntaxe
     * "factor*query" n'est pas support�e.
     *
     * Cette fonction est un analyseur simpliste permettant d'analyser (� coup
     * d'xpressions r�guli�res) une requ�te de boost.
     *
     * L'�quation � analyser doit �tre une suite de clauses de la forme :
     * NomIndex:factor*terme ou NomIndex:(factor*terme factor*terme ...)
     *
     * Exemple : "DatEdit:3.14159*2009 DatEdit:(9*2008 7*2007) TypDoc:5*article"
     *
     * Pour �tre analys�e correctement, la requ�te ne doit rien contenir
     * d'autre (pas d'op�rateurs, pas de troncature, pas de guillemets, etc.)
     *
     * Les noms d'index utilis�s doivent obligatoirement �tre des index de base
     * (les alias ne sont pas support�s).
     *
     * Les facteurs de pond�ration utilis�s sont soit des entiers, soit des
     * r�els (utiliser un point comme s�parateur pour les d�cimales, pas une
     * virgule) et doivent obligatoirement �tre sup�rieurs ou �gaux � z�ro.
     *
     * @param $boost
     * @return XapianQuery
     * @throws Exception si l'�quation de boost n'est pas valide.
     */
    private function parseBoost($boost)
    {
        if (is_null($boost) || trim($boost)==='') return null;

        if (! preg_match_all('~(\w+):\s*(?:\(\s*(.*)\s*\)|([^\s]+))~s', $boost, $matches, PREG_SET_ORDER))
            throw new Exception('Equation de boost incorrecte : ' . $boost);

        $q=array();
        foreach($matches as $match)
        {
            $index=$match[1];
            $value=$match[2] or $value=$match[3];

            // D�termine le pr�fixe de l'index
            $index=Utils::ConvertString($index, 'alphanum');
            if (!isset($this->schema->indices[$index]))
                throw new Exception("Boost incorrect : l'index $index n'existe pas");
            $prefix=$this->schema->indices[$index]->_id . ':';

            if (! preg_match_all('~(\d+(?:\.\d+)?)\*([A-Za-z0-9]+)~', $value, $matches, PREG_SET_ORDER))
                throw new Exception('valeur incorrecte pour le boost : ' . $value);

            foreach($matches as $match)
            {
                $weight=$match[1];
                $term=$match[2];

                $q[]=new XapianQuery(XapianQuery::OP_SCALE_WEIGHT, new XapianQuery($prefix . $term), (float)$weight);
            }
        }
        $boost = new XapianQuery(XapianQuery::OP_OR, $q);

        return $boost;
    }


    /**
     * Retourne la liste des options de recherche reconnues par {@link search()}
     * et leur valeur par d�faut.
     *
     * @return array
     */
    public function getDefaultOptions()
    {
        return array
        (
            'sort'              => '-',
            'start'             => 1,
            'max'               => 10,
            'filter'            => null,
            'minscore'          => 0,
            'rset'              => null,
            'defaultop'         => 'OR',
            'opanycase'         => true,
            'defaultindex'      => null,
            'checkatleast'      => 100,
            'facets'            => null,
            'boost'             => null,
//            'equation'          => null,
            'auto'              => null,
            'defaultequation'   => null,
            'defaultfilter'     => null,
            'autosort'          => '-', // tri auto : ordre pour une requ�te bool�enne, cf setSortOrder.
            'docset'            => null, // un tableau de termes : array('REF'=>array(1,2,3,4...));
        );
    }

    /**
     * @inheritdoc
     */
    public function search($equation=null, $options=null)
    {
        timer && Timer::enter();
        timer && Timer::enter('Initialisation');

        // Combine les options de recherche pass�es en param�tre avec les options par d�faut
        if (is_null($options))
        {
            $options = $this->getDefaultOptions();
        }
        else
        {
            // Supprime des options les valeurs "null" pass�es en param�tre
            // Sinon, on ne r�cup�rera pas la valeur par d�faut dans ce cas.
            foreach($options as $key=>$value)
                if ($value===null or $value==='' or $value===array())
                    unset($options[$key]);

            // Combine les options pass�es en param�tre avec les options par d�faut
            $options = $options + $this->getDefaultOptions();
        }

        // Cr�e un objet Request � partir du tableau d'options
        $this->options = Request::create($options);        // fixme: remplacer l'objet request par un objet Parameters

        // Si une �quation nous a �t� transmise en param�tre, on la
        // stocke comme une option
        if ($equation) $this->options->add('equation', $equation);

        // Valide la valeur de chacune des options
        $this->options
            ->asArray('equation')->set()
            ->asArray('sort')->set()
            ->unique('start')->int()->min(1)->set()
            ->unique('max')->int()->min(-1)->set()
            ->asArray('filter')->set()
            ->unique('minscore')->int()->min(0)->max(100)->set()
            ->asArray('rset')->int()->min(1)->set()
            ->unique('defaultop')->oneof('AND','OR')->set()
            ->unique('opanycase')->bool()->set()
            ->unique('defaultindex')->set()
            ->unique('checkatleast')->defaults($this->options->max+1)->int()->min(-1)->set()
            ->asArray('facets')->set()
            ->unique('boost')->set()
            ->unique('autosort')->set()
            // docset : n'est pris en compte que si c'est un tableau de tableaux
            ;

        // Traduit l'op�rateur par d�faut (defaultop) en op�rateur Xapian (defaultopcode)
        $this->options->defaultopcode=
            $this->options->convert('defaultop', array('and'=>XapianQuery::OP_AND,'or'=>XapianQuery::OP_OR))->ok();

        // Ajuste start pour que ce soit un multiple de max
        if ($this->options->max > 1)
        {
            /* explication : si on est sur la 2nde page avec max=10, on affiche
             * la 11�me r�ponse en premier. Si on demande alors � passer � 50
             * notices par page, on va alors afficher les notices 11 � 50, mais
             * on n'aura pas de lien "page pr�c�dente".
             * Le code ci-dessus, dans ce cas, ram�ne "start" � 1 pour que toutes
             * les notices soient affich�es.
             */
            $this->options->start=$this->options->start-(($this->options->start-1) % $this->options->max);
        }

//        echo '<h1>Contexte de recherche</h1><pre>', var_export($this->options->getParameters(),true), '</pre>';

        // Met en place l'environnement de recherche lors de la premi�re recherche
        if (is_null($this->xapianEnquire)) $this->setupSearch();

        // Cr�e la requ�te � ex�cuter en fonction des options indiqu�es
        $this->setupRequest();

        // D�finit la requ�te � ex�cuter
        $this->xapianEnquire->set_query($this->xapianQuery);

        // Si des documents pertinents ont �t� indiqu�s, cr�e le rset correspondant
        $rset=null;
        if ($t=$this->options->rset)
        {
            $rset=new XapianRset();
            foreach($t as $id)
                $rset->add_document($id);
        }

        // a priori, pas de r�ponses
        $this->eof=true;

        // D�finit l'ordre de tri des r�ponses

        // Probl�me : une recherche '*' tri�e par pertinence est lente (> 30s).
        // Force ici un tri par docid d�croissant dans ce cas.
        // if ($this->isMatchAll($this->xapianQuery) && $this->options->sort===array('%')) $this->options->set('sort', array('-'));
        // Depuis xapian >= 1.0.9, ce n'est plus n�cessaire.

        $this->setSortOrder($this->options->sort);

        // D�finit le score minimal souhait�
        if ($t=$this->options->minscore) $this->xapianEnquire->set_cutoff($t);

        timer && Timer::leave('Initialisation');

        // Lance la recherche

        // Exp�rimental : support des facettes de la recherche via un TermCountMatchSpy.
        // Requiert la version "MatchSpy" de Xapian (en attendant que la branche
        // MatchSpy ait �t� int�gr�e dans le trunk.
        if ($t=$this->options->facets && function_exists('new_TermCountMatchSpy'))
        {
            // Fonctionnement : on d�finit dans la config une cl� facets qui
            // indique les tables de lookup qu'on souhaite utiliser comme facettes.
            // DatabaseModule::select() nous passe cette liste dans le param�tre
            // '_facet' du tableau options.
            // On cr�e un Spy de type XapianTermCountMatchSpy auquel on
            // demande de compter tous les termes provenant de ces tables
            // de lookup.
            // L'utilisateur peut ensuite r�cup�rer le r�sultat en utilisant
            // la m�thode getFacet() et en appellant searchInfo() avec les
            // nouveaus param�tres spy* introduits.
            $this->spy=new XapianTermCountMatchSpy();
            foreach($t as $table)
            {
                $key=Utils::ConvertString($table, 'alphanum');
                if (!isset($this->schema->lookuptables[$key]))
                    throw new Exception("La table de lookup '$table' n'existe pas");
                $prefix='T' . $this->schema->lookuptables[$key]->_id . ':';
                $this->spy->add_prefix($prefix);
            }
            $this->xapianMSet=$this->xapianEnquire->get_MSet($this->options->start-1, $this->options->max, 1000, $rset, null, $this->spy);
        }

        // Recherche standard sans facettes
        else
        {
            $this->spy=null;
            timer && Timer::enter('Get_MSet');
            $this->xapianMSet=$this->xapianEnquire->get_MSet($this->options->start-1, $this->options->max, $this->options->checkatleast, $rset);
            timer && Timer::leave('Get_MSet');
        }

        timer && Timer::enter('Finalisation');

        // D�termine le nombre de r�ponses obtenues
        $this->count=$this->xapianMSet->get_matches_estimated();

        // Si on n'a aucune r�ponse parce que start �tait "trop grand", r�-essaie en ajustant start
        if ($this->xapianMSet->is_empty() && $this->count > 1 && $this->options->start > $this->count)
        {
            // le mset est vide, mais on a des r�ponses (count > 0) et le start
            // demand� �tait sup�rieur au count obtenu.

            // Modifie start pour qu'il corresponde � la premi�re r�ponse de la derni�re page
            $this->options->start=$this->count-(($this->count-1) % $this->options->max);

            // Relance la recherche
            timer && Timer::enter('Get_MSet (avec ajustement de start)');
            $this->xapianMSet=$this->xapianEnquire->get_MSet($this->options->start-1, $this->options->max, $this->options->checkatleast, $rset);
            timer && Timer::leave('Get_MSet (avec ajustement de start)');
        }

        // Si on n'a aucune r�ponse, retourne false
        if ($this->xapianMSet->is_empty())
        {
            $this->xapianMSetIterator=null;
            $this->count=0;
            $result=false;
        }

        // Retourne true pour indiquer qu'on a au moins une r�ponse
        else
        {
            $this->xapianMSetIterator=$this->xapianMSet->begin();
            $this->loadDocument();
            $this->eof=false;
            $result=true;
        }

        timer && Timer::leave('Finalisation');
        timer && Timer::leave();
        return $result;
    }

    /**
     * Retourne un nuage de tags pour le ou les champs indiqu�s.
     *
     * La m�thode ne peut �tre appell�e qu'apr�s qu'une recherche ait �t�
     * ex�cut�e. Elle relance la recherche en cours en for�ant un tri par
     * pertinence et en g�n�rant un MSet contenant $checkAtLeast r�ponses.
     *
     * Elle parcourt ensuite toutes les r�ponses obtenues et stocke les
     * diff�rentes valeurs rencontr�es dans les champs ($fields) demand�s.
     *
     * Les valeurs rencontr�es pour chaque champ sont ensuite tri�es par
     * fr�quences d�croissantes et filtr�es.
     *
     * Le filtrage consiste � :
     * - supprimer les mots et les articles qui figurent d�j� dans la requ�te de
     *   l'utilisateur.
     * - supprimer les termes qui ont une fr�quence dans la base sup�rieure �
     *   la fr�quence maximale autoris�e (cf plus bas, format de $fields).
     * - supprimer les termes qui correspondent � l'expression r�guli�re
     *   ($ignoreRegExp) indiqu�e.
     *
     * @param string|array $fields un ou plusieurs champs pour lesquels vous
     * voulez r�cup�rer un nuage de tags. Si $fields est un tableau, le nom de
     * chaque champ peut �tre indiqu� soit dans la cl�, soit dans la valeur.
     * S'il est indiqu� dans la cl�, la valeur indique alors une fr�quence
     * maximale (sous forme d'un pourcentage entre 0 et 100) et les termes ayant
     * dans la base une fr�quence sup�rieure � ce seuil seront ignor�s.
     *
     * @param int $max le nombre maximum de tags dans chaque nuage (z�ro = pas de
     * limite).
     *
     * @param int $checkAtLeast le nombre minimum de documents � examiner.
     *
     * @param string $ignoreRegExp expression r�guli�re utilis�e pour ignorer
     * certains termes. Les termes qui correspondent � l'expression r�guli�re
     * indiqu�e seront ignor�s.
     *
     * @return array soit un tableau de tags (si $fields est une chaine) sous la
     * forme tag=>occurences, soit un tableau de tableaux de tags sous la forme
     * field =>array($tag=>occurences). Dans les deux cas, les tags obtenus sont
     * tri�s par fr�quence d�croissante.
     */
    public function getTags($fields, $max=25, $checkAtLeast=50, $ignoreRegExp=null, $excludeExistingTerms = true)
    {
        timer && Timer::enter();

        // D�termine l'ID de chacun des champs demand�s
        $result=$maxFrequency=$fieldsId=$indexId=array();
        foreach((array)$fields as $key=>$value)
        {
            // champ de la forme Field=>maxFrequency
            if (is_string($key))
            {
                $name=$key;
                $maxFreq=$value;
            }

            // champ de la forme int=>Field
            else
            {
                $name=$value;
                $maxFreq=0;
            }

            // V�rifie que le champ demand� existe
            $key=Utils::ConvertString($name, 'alphanum');
            if (! isset($this->schema->fields[$key]))
                throw new Exception("Le champ $name n'existe pas");

            // Stocke l'id du champ
            $id=$this->schema->fields[$key]->_id;
            $result[$name]=array();
            $fieldsId[$id] = &$result[$name];

            // D�termine l'ID de l'index correspondant au champ (s'il existe)
            if (isset($this->schema->indices[$key]))
                $indexId[$id]=$this->schema->indices[$key]->_id;
            else
                $indexId[$id]=0;

            $maxFrequency[$id]=$maxFreq;
        }

        // R�cup�re les termes de la recherche en cours
        $searchTerms=array_flip($this->searchInfo('internalqueryterms'));

        // D�finit un tri par pertinence
        $this->setSortOrder('%');

        // Relance la recherche
        timer && Timer::enter('tags.get_MSet');
        $mSet=$this->xapianEnquire->get_MSet(0, $checkAtLeast, $checkAtLeast/*, todo: $rset*/);
        timer && Timer::leave('tags.get_MSet');

        // Parcourt les notices
        timer && Timer::enter('tags.loop');
        $begin=$mSet->begin();
        $end=$mSet->end();

        while (! $begin->equals($end))
        {
            $data=unserialize($begin->get_document()->get_data());
            foreach($fieldsId as $id=>&$tags)
            {
                if (! isset($data[$id])) continue;
                if (empty($data[$id])) continue;
                foreach((array)$data[$id] as $tag)
                {
                    // Met � jour le nombre d'occurences de ce tag
                    if (isset($tags[$tag]))
                        ++$tags[$tag];
                    else
                        $tags[$tag]=1;
                }
            }
            $begin->next();
        }

        // Teste s'il faut garder chacun des tags obtenus
        $docCount=$this->xapianDatabase->get_doccount();
        foreach($fieldsId as $id=>&$tags)
        {
            // Trie le tableau par occurences d�croissantes
            arsort($tags, SORT_NUMERIC);

            $nb=0;
            foreach($tags as $tag=>$freq)
            {
                // Tolenize le tag
                $tokens=Utils::tokenize($tag);

                // Si le tag est un mot unique d�j� pr�sent dans la requ�te, on l'ignore
                if ($excludeExistingTerms )
                {
                    if (count($tokens)===1)
                    {
                        $term=$indexId[$id] . ':' . current($tokens);
                        if (isset($searchTerms[$term]))
                        {
                            unset($tags[$tag]);
                            continue;
                        }
                    }
                }

                // Si le tag est un article d�j� pr�sent dans la requ�te, on l'ignore
                $term = $indexId[$id].':_' . implode('_', $tokens) . '_';
                if ($excludeExistingTerms)
                {
                    if (isset($searchTerms[$term]))
                    {
                        unset($tags[$tag]);
                        continue;
                    }
                }

                // Si le tag matche l'expression r�guli�re indiqu�e, on l'ignore
                if ($ignoreRegExp && preg_match($ignoreRegExp, $tag))
                {
                    unset($tags[$tag]);
                    continue;
                }

                // Si le tag est pr�sent dans plus de x% des notices, on l'ignore
                if ($maxFrequency[$id])
                {
                    $percent=round(100 * $this->xapianDatabase->get_termfreq($term) / $docCount);
                    if ($percent > $maxFrequency[$id])
                    {
                        unset($tags[$tag]);
                        continue;
                    }
                }

                // Si on a obtenu plus de $max tag, termin�
                ++$nb;
                if ($max && $nb >= $max) break;
            }

            // Tronque le tableau si n�cessaire
            if ($max && count($tags)>$max)
                $tags=array_slice($tags, 0, $max, true);
        }
        timer && Timer::leave('tags.loop');

        // Remet l'ordre de tri initial tel qu'il �tait (peu utile mais au cas o�...)
        $this->setSortOrder($this->options->sort);

        timer && Timer::leave();

        return is_array($fields) ? $result : array_pop($result);
    }


    /**
     * Indique si la requ�te en cours contient au moins un terme probabiliste.
     *
     * La m�thode examine les termes pr�sents dans la requ�te en cours et
     * retourne vrai si au moins l'un de ces termes porte sur un index de type
     * probabiliste.
     *
     * @return bool
     */
    public function isProbabilisticQuery()
    {
        // R�cup�re la liste des termes de la requ�te
        $terms=$this->searchInfo('internalqueryterms');

        if (empty($terms)) return false;
        // xapian < 1.0.11 avait un bug qui faisait que le terme '' �tait retourn�
        // si on avait une requ�te du type MatchAll. Pour contourner, il fallait
        // tester si terms �tait un tableau vide ou un tableau contenant une
        // chaine vide. C'est inutile maintenant.

        // Constitue la liste des id d'index pr�sents dans les termes
        $t=array();
        foreach($terms as $term)
        {
            if (false === $pt=strpos($term, ':')) continue;
            $t[(int) substr($term, 0, $pt)] = true;
        }

        // Parcourt tous les index et teste si on en trouve un de type probabiliste
        foreach($this->schema->indices as $index)
        {
            if (isset($index->_type) && $index->_type===DatabaseSchema::INDEX_BOOLEAN) continue;

            if (isset($t[$index->_id])) return true;
        }

        // Pas de terme dans la requ�te
        return false;
    }

    /**
     * Cr�e la requ�te xapian � ex�cuter en fonction des options de recherches
     * indiqu�es.
     *
     * La m�thode cr�e la requ�te finale qui sera ex�cut�e par xapian en prenant
     * en compte :
     *
     * - la ou les �quations de recherche indiqu�es dans l'option "equation" ;
     * - les param�tres indiqu�s dans l'option "auto" qui correspondent � un
     *   nom d'index ou d'alias existant ;
     * - le ou les filtres indiqu�s dans l'option "filter" ;
     * - le ou les filtres par d�faut indiqu�s dans l'option "defaultfilter" ;
     * - le boost �ventuel indiqu� dans l'option "boost".
     *
     * La requ�te xapian � ex�cuter est stock�e dans {@link $xapianQuery}.
     */
    private function setupRequest()
    {
    /*
        Combinatoire utilis�e pour construire l'�quation de recherche :
        +----------+-----------------+---------------------+-------------------+
        | Type de  |    Op�rateur    | Op�rateur entre les |  Op�rateur entre  |
        | requ�te  | entre les mots  |  valeurs d'un champ | champs diff�rents |
        +----------+-----------------+---------------------+-------------------+
        |   PROB   |    default op   |        AND          |        AND        |
        +----------+-----------------+---------------------+-------------------+
        |   BOOL   |    default op   |        OR           |        AND        |
        +----------+-----------------+---------------------+-------------------+
        |   LOVE   |    default op   |        AND          |        AND        |
        +----------+-----------------+---------------------+-------------------+
        |   HATE   |    default op   |        OR           |        OR         |
        +----------+-----------------+---------------------+-------------------+
     */

        timer && Timer::enter();

        // "equation" : la ou les �quations pass�es en query string dans _equation
        $query=$this->parseQuery($this->options->equation, $this->options->defaultopcode, XapianQuery::OP_AND);

        // "auto" : index et alias pass�s en query string
        if ($this->options->auto)
        {
            $love=$hate=$prob=$bool=null;

            // Parcourt tous les param�tres
            foreach($this->options->auto as $name=>$value)
            {
                if ($value===null or $value==='' or $value===array()) continue;

                // Ne garde que ceux qui correspondent � un nom d'index ou d'alias existant
                $indexName=strtolower(ltrim($name,'+-'));
                if (isset($this->schema->indices[$indexName]))
                    $index=$this->schema->indices[$indexName];
                elseif(isset($this->schema->aliases[$indexName]))
                    $index=$this->schema->aliases[$indexName];
                else
                    continue;

                // D�termine comment il faut analyser la requ�te et o� la stocker
                switch(substr($name, 0, 1))
                {
                    case '+': // Tous les mots sont requis, donc on parse en "ET"
                        $q=$this->parseQuery($value, $this->options->defaultopcode, XapianQuery::OP_AND, $indexName);
                        if (!is_null($q)) $love[]=$q;
                        break;

                    case '-': // le r�sultat sera combin� en "AND_NOT hate", donc on parse en "OU"
                        $q=$this->parseQuery($value, $this->options->defaultopcode, XapianQuery::OP_OR, $indexName);
                        if (!is_null($q)) $hate[]=$q;
                        break;

                    default: // parse en utilisant le default op
                        if (isset($index->_type) && $index->_type === DatabaseSchema::INDEX_BOOLEAN)
                        {
                            $q=$this->parseQuery($value, $this->options->defaultopcode, XapianQuery::OP_OR, $indexName);
                            if (!is_null($q)) $bool[]=$q;
                        }
                        else
                        {
                            $q=$this->parseQuery($value, $this->options->defaultopcode, XapianQuery::OP_AND, $indexName);
                            if (!is_null($q)) $prob[]=$q;
                        }
                        break;
                }
            }

            // Combine entres elles les �quations de m�me nature (les love ensembles, les prob ensembles, etc.)
            foreach(array
            (
                'love'=>XapianQuery::OP_AND,
                'hate'=>XapianQuery::OP_OR,
                'prob'=>XapianQuery::OP_AND,
                'bool'=>XapianQuery::OP_AND,
            ) as $type=>$op)
            {
                // Aucune requ�te de type $type, rien � faire
                if (count($$type)===0) continue;

                // Une seule requ�te de type $type. C'est d�j� un objet XapianQuery, on l'utilise tel quel
                if (count($$type)==1)
                    $$type=array_pop($$type);

                // Plusieurs requ�tes de type $type : on combine tous les XapianQuery en une seule avec $op
                else
                    $$type=new XapianQuery($op, $$type);
            }

            // Cr�e la partie principale de la requ�te sous la forme :
            // ((query AND love AND_MAYBE prob) AND_NOT hate) FILTER bool
            // Si defaultop=AND, le AND_MAYBE devient OP_AND
            if ($love || $query)
            {
                if ($love)
                {
                    if (is_null($query) || $this->isMatchAll($query))
                        $query=$love;
                    else
                        $query=new XapianQuery(XapianQuery::OP_AND, $query, $love);
                }

                if ($prob)
                {
                    if (is_null($query) || $this->isMatchAll($query))
                        $query=$prob;
                    elseif ($this->options->defaultopcode===XapianQuery::OP_OR)
                        $query=new XapianQuery(XapianQuery::OP_AND_MAYBE, $query, $prob);
                    else
                        $query=new XapianQuery(XapianQuery::OP_AND, $query, $prob);
                }
            }
            else
            {
                $query=$prob;
            }

            if ($hate)
            {
                // on ne peut pas faire null AND_NOT xxx. Si query est null, cr�e une query '*'
                if (is_null($query)) $query=new XapianQuery('');
                $query=new XapianQuery(XapianQuery::OP_AND_NOT, $query, $hate);
            }

            if ($bool)
            {
                if (is_null($query) || $this->isMatchAll($query))
                    $query=$bool;
                else
                    $query=new XapianQuery(XapianQuery::OP_FILTER, $query, $bool);
            }
        }

        // filter : filtres utilisateur indiqu�s en query string
        $filter=$this->parseQuery($this->options->filter, XapianQuery::OP_OR, XapianQuery::OP_AND);
        if ($filter)
        {
            if (is_null($query) || $this->isMatchAll($query))
                $query=$filter;
            else
                $query=new XapianQuery(XapianQuery::OP_FILTER, $query, $filter);
        }

        // DocSet. tableau de tableaux. la cl� indique l'index
        if (is_array($this->options->docset))
        {
            $docset = null;
            foreach($this->options->docset as $index=>$values)
            {
                $terms = array();

                // D�termine le pr�fixe de l'index
                $index=Utils::ConvertString($index, 'alphanum');
                if (!isset($this->schema->indices[$index]))
                    throw new Exception("DocSet incorrect : l'index $index n'existe pas");
                $prefix=$this->schema->indices[$index]->_id . ':';

                foreach((array)$values as $value)
                {
                    $terms[]=$prefix . $value; // todo faire un convertString ?
                }

                if (is_null($docset))
                    $docset = new XapianQuery(XapianQuery::OP_OR, $terms); // todo: OP_SYNONYM
                else
                    $docset = new XapianQuery(XapianQuery::OP_AND, XapianQuery(XapianQuery::OP_OR, $terms));
            }
            // echo 'DocSet : <pre>', $docset->get_description(), '</pre>';
            if (is_null($query))
                $query = $docset;
            else
                $query=new XapianQuery(XapianQuery::OP_FILTER, $query, $docset);
        }

        // defaultequation : si on n'a toujours pas de requ�te, utilise l'�quation par d�faut
        if (is_null($query))
        {
            $query=$this->parseQuery($this->options->defaultequation, XapianQuery::OP_AND, XapianQuery::OP_AND);
            if (is_null($query))
                throw new Exception('Vous n\'avez indiqu� aucun crit�re de s�lection.');
        }

        // defaultfilter : filtres par d�faut indiqu�s dans la config
        $defaultFilter=$this->parseQuery($this->options->defaultfilter, XapianQuery::OP_OR, XapianQuery::OP_AND);
        if ($defaultFilter)
        {
            if (is_null($query) || $this->isMatchAll($query))
                $query=$defaultFilter;
            else
                $query=new XapianQuery(XapianQuery::OP_FILTER, $query, $defaultFilter);
        }

        // Prend en compte le boost �ventuel si on est en tri par pertinence
        if ($this->options->boost && $this->options->sort===array('%'))
            $query=new XapianQuery(XapianQuery::OP_AND_MAYBE, $query, $this->parseBoost($this->options->boost));

        $this->query=new UserQuery($this->options->getParameters(), $this->schema);

        // Stocke la requ�te finale
        $this->xapianQuery=$query;

        timer && Timer::leave();
    }


    /**
     * Traduit la requ�te interne g�n�r�e par Xapian en �quation de recherche.
     *
     * Cette fonction est utilis�e en interne par
     * {@link searchInfo('ExplainQuery')} pour expliquer la requ�te �
     * l'utilisateur.
     *
     * @param XapianQuery $query la requ�te � examiner.
     * @return string
     */
    private function explainQuery(XapianQuery $query)
    {
        if (is_null($query)) return '';

        // R�cup�re la description de la requ�te Xapian
        $h=$query->get_description();

        // Supprime le libell� "XapianQuery()" et le premier niveau de parenth�ses
        if (substr($h, 0, 14) === 'Xapian::Query(') $h=substr($h, 14, -1);

        // Supprime les mentions "(pos=n)" pr�sentes dans la requ�te
        $h=preg_replace('~:\(pos=\d+?\)~', '', $h);

        // Reconstruit les expressions entre guillemets
        $h=preg_replace_callback('~\((\d+:)[a-z0-9@_]+(?: (PHRASE \d+ )\1[a-z0-9@_]+)+\)~', array($this, 'makePhrase'), $h);

        // Reconstruit les recherches � l'article
        //echo "<br /><br /><br /><pre>";var_export($h);echo '</pre>';
        $h=preg_replace_callback('~_[a-z0-9@_]+_~', array($this, 'makeValue'), $h);

        // Traduits les pr�fixes utilis�s en noms de champs
        $h=preg_replace_callback('~(\d+):~',array($this,'idToName'),$h);

        // Met les op�rateurs bool�ens en gras
        $h=preg_replace('~AND_MAYBE|AND_NOT|FILTER|AND|OR|PHRASE \d+~', '<strong>$0</strong>', $h);

        // Si l'expression obtenue commence par une parenth�se, c'est qu'on a un niveau de parenth�ses en trop
        if (substr($h,0,1)==='(' && substr($h, -1)===')')
        {
            $h = substr($h, 1, -1);
        }

        // Va � la ligne et indente � chaque niveau de parenth�se
        $h=strtr
        (
            $h,
            array
            (
                '('=>'<br />(<div style="margin-left: 2em;">',
                ')'=>'</div>)<br />',
            )
        );

        // Supprime les <br /> superflus
        $h=str_replace('<br /><br />', '<br />', $h);
        $h=preg_replace('~^<br />|<br />$~', '', $h);
        $h=preg_replace('~(<div[^>]*>)<br />(\(<div)~', '$1$2', $h);

        // Retourne le r�sultat
        return $h;
    }

    /**
     * Callback utilis� par {@link explainQuery()}.
     *
     * Reconstruit une recherche "� l'article" � partir de la description faite
     * par Xapian de la requ�te ex�cut�e.
     *
     * Exemple : _a_b_ -> [a b]
     *
     * @param array $matches le tableau g�n�r� par preg_replace_callback
     * @return string
     */
    private function makeValue($matches)
    {
        return '[' . trim(strtr($matches[0], '_', ' ')) . ']';
    }

    /**
     * Callback utilis� par {@link explainQuery()}.
     *
     * Reconstruit une expression entre guillemets � partir de la description faite
     * par Xapian de la requ�te ex�cut�e.
     *
     * Exemple : a PHRASE 2 b -> "a b"
     *
     * @param array $matches le tableau g�n�r� par preg_replace_callback
     * @return string
     */
    private function makePhrase($matches)
    {
        $query = substr($matches[0], 1, -1); // l'expression est toujours entour�e de parenth�ses (inutiles)
        $id = $matches[1];
        $op = $matches[2];
        $result = $id . '"' . strtr($query, array($id=>'', $op=>'')) . '"';
        return $result;
    }

    /**
     * Param�tre le MSet pour qu'il retourne les documents selon l'ordre de tri
     * indiqu� en param�tre.
     *
     * @param string|array $sort un tableau ou une chaine indiquant les
     * diff�rents crit�res composant l'ordre de tri souhait�.
     *
     * Les crit�res de tri possible sont :
     * - <code>%</code> : trier les notices par pertinence (la meilleure en t�te)
     * - <code>+</code> : trier par ordre croissant des num�ros de document
     * - <code>-</code> : trier par ordre d�croissant des num�ros de document
     * - <code>xxx+</code> : trier sur le champ xxx, par ordre croissant
     *   (le signe plus est optionnel, c'est l'ordre par d�faut)
     * - <code>xxx-</code> : trier sur le champ xxx, par ordre d�croissant
     *
     * Plusieurs crit�res de tri peuvent �tre combin�s entres eux. Dans ce cas,
     * le premier crit�re sera d'abord utilis�, puis, en cas d'�galit�, le
     * second et ainsi de suite.
     *
     * La combinaison des crit�res peut se faire soit en passant en param�tre
     * une chaine listant dans l'ordre les diff�rents crit�res, soit en passant
     * en param�tre un tableau contenant autant d'�l�ments que de crit�res ;
     * soit en combinant les deux.
     *
     * Exemple de crit�res composites :
     * - chaine : <code>'type'</code>, <code>'type+ date- %'</code>
     * - tableau : <code>array('type', 'date+')</code>,
     *   <code>array('type', 'date+ revue+ titre %'</code>
     *
     * Remarque : n'importe quel caract�re de ponctuation peut �tre utilis�
     * pour s�parer les diff�rents crit�res au sein d'une m�me chaine (espace,
     * virgule, point-virgule...)
     *
     * @throws Exception si l'ordre de tri demand� n'est pas possible ou si
     * la cl� de tri indiqu�e n'existe pas dans la base.
     */
    private function setSortOrder($sort)
    {
        // Si $sort est un tableau, on concat�ne tous les �l�ments ensembles
        if (is_array($sort))
            $sort=implode(',', $sort);

        // Cas particulier : tri "auto"
        if ($sort==='auto')
        {
            if ($this->isProbabilisticQuery())
            {
                $this->setSortOrder('%');
                $this->sortOrder='auto (%)';
            }
            else
            {
                $this->setSortOrder($this->options->autosort);
                $this->sortOrder='auto (' . $this->sortOrder . ')';
            }
            return;
        }

        // On a une chaine unique avec tous les crit�res, on l'explose
        $t=preg_split('~[^a-zA-Z_%+-]+~m', $sort, -1, PREG_SPLIT_NO_EMPTY);

        // Cas d'un tri simple (un seul crit�re indiqu�)
        $this->sortKey=array();
        if (count($t)===1)
        {
            $this->sortOrder = $key = $t[0];
            switch ($key)
            {
                // Par pertinence
                case '%':
//                    $this->xapianEnquire->set_weighting_scheme
//                    (
//                        new XapianBM25Weight
//                        (
//                            1.0, // k1  governs the importance of within document frequency. Must be >= 0. 0 means ignore wdf. Default is 1.
//                            0.0, // k2  compensation factor for the high wdf values in large documents. Must be >= 0. 0 means no compensation. Default is 0.
//                            1.0, // k3  governs the importance of within query frequency. Must be >= 0. 0 means ignore wqf. Default is 1.
//                            0.0, // b   Relative importance of within document frequency and document length. Must be >= 0 and <= 1. Default is 0.5.
//                            0.5  // min_normlen specifies a cutoff on the minimum value that can be used for a normalised document length - smaller values will be forced up to this cutoff. This prevents very small documents getting a huge bonus weight. Default is 0.5.
//                        )
//                    );
//                    $this->xapianEnquire->set_weighting_scheme(new XapianBoolWeight());
//                    $this->xapianEnquire->set_weighting_scheme(new XapianTradWeight(0.0));
                    $this->xapianEnquire->set_Sort_By_Relevance();
                    break;

                // Par docid croissants
                case '+':
                    $this->xapianEnquire->set_weighting_scheme(new XapianBoolWeight());
                    $this->xapianEnquire->set_DocId_Order(XapianEnquire::ASCENDING);
                    break;

                // Par docid d�croissants
                case '-':
                    $this->xapianEnquire->set_weighting_scheme(new XapianBoolWeight());
                    $this->xapianEnquire->set_DocId_Order(XapianEnquire::DESCENDING);
                    break;

                // Sur une cl� de tri existante
                default:
                    // D�termine l'ordre (croissant/d�croissant)
                    $lastChar=substr($key, -1);
                    $reverse=false;
                    if ($lastChar==='+' || $lastChar==='-')
                    {
                        $key=substr($key, 0, -1);
                        if ($lastChar==='-') $reverse=true;
                    }

                    // V�rifie que la cl� de tri existe dans la base
                    $key=strtolower($key);
                    if (! isset($this->schema->sortkeys[$key]))
                        throw new Exception('Impossible de trier par : ' . $key);

                    // R�cup�re l'id de la cl� de tri (= le value slot number � utiliser)
                    $id=$this->schema->sortkeys[$key]->_id;

                    // Trie sur cette valeur
                    $this->xapianEnquire->set_sort_by_value($id, $reverse);

                    // M�morise l'ordre de tri en cours (pour searchInfo)
                    $this->sortOrder=$key . ($reverse ? '-' : '+');
                    $this->sortKey[$key]=$id;
            }
        }

        // Cas d'un tri composite (plusieurs crit�res de tri)
        else
        {
            // On va utiliser un sorter xapian pour cr�er la cl�
            $this->xapianSorter=new XapianMultiValueSorter();

            // R�initialise l'ordre de tri en cours
            $this->sortOrder='';

            // On va utiliser la m�thode set_sort_by_key sauf s'il faut combiner avec la pertinence
            $function='set_sort_by_key';

            // Ajoute chaque crit�re de tri au sorter
            foreach($t as $i=>$key)
            {
                switch ($key)
                {
                    // Par pertinence : change la m�thode � utiliser
                    case '%':
                        if ($i===0)
                            $method='set_sort_by_relevance_then_key';
                        elseif($i===count($t)-1)
                            $method='set_sort_by_key_then_relevance';
                        else
                            throw new Exception('Ordre de tri incorrect "'.$sort.'" : "%" peut �tre au d�but ou � la fin mais pas au milieu');

                        $this->sortOrder.=$key . ' ';
                        break;

                    // Par docid : impossible, on ne peut pas combiner avec autre chose
                    case '+':
                    case '-':
                        throw new Exception('Ordre de tri incorrect "'.$sort.'" : "'.$key.'" ne peut pas �tre utilis� avec d\'autres crit�res');
                        break;

                    // Sur une cl� de tri existante
                    default:
                        // D�termine l'ordre (croissant/d�croissant)
                        $lastChar=substr($key, -1);
                        $forward=true;
                        if ($lastChar==='+' || $lastChar==='-')
                        {
                            $key=substr($key, 0, -1);
                            $forward=($lastChar==='+');
                        }

                        // V�rifie que la cl� de tri existe dans la base
                        $key=strtolower($key);
                        if (! isset($this->schema->sortkeys[$key]))
                            throw new Exception('Impossible de trier par : ' . $key);

                        // R�cup�re l'id de la cl� de tri (= le value slot number � utiliser)
                        $id=$this->schema->sortkeys[$key]->_id;

                        // Ajoute cette cl� au sorter
                        $this->xapianSorter->add($id, $forward);

                        // M�morise l'ordre de tri en cours (pour searchInfo)
                        $this->sortOrder.=$key . ($forward ? '+ ' : '- ');
                        $this->sortKey[$key]=$id;
                }
            }

            // Demande � xapian de trier en utilisant la m�thode et le sorter obtenu
            $this->xapianEnquire->$function($this->xapianSorter, false);

            // Supprime l'espace final de l'ordre en cours
            $this->sortOrder=trim($this->sortOrder);
        }
    }


    /**
     * Sugg�re des termes provenant de la table indiqu�e
     *
     * @param string $table
     * @return array
     */
    public function suggestTerms($table)
    {
        // V�rifie qu'on a un enregistrement en cours
        if (is_null($this->xapianMSetIterator))
            throw new Exception('Pas de document courant');

        // D�termine le pr�fixe de la table dans laquelle on veut chercher
        if ($table)
        {
            $key=Utils::ConvertString($table, 'alphanum');
            if (!isset($this->schema->lookuptables[$key]))
                throw new Exception("La table de lookup '$table' n'existe pas");
            $prefix='T' . $this->schema->lookuptables[$key]->_id . ':';
        }

        $rset=new XapianRset();

        $it=$this->xapianMSet->begin();
//        $nb=0;
        while (!$it->equals($this->xapianMSet->end()))
        {
            $rset->add_document($it->get_docid());
//            $nb++;
//            if ($nb>5) break;
            $it->next();
        }

        $eset=$this->xapianEnquire->get_eset(100, $rset);
        $terms=array();
        $it=$eset->begin();
        while (!$it->equals($eset->end()))
        {
            $term=$it->get_term();
            if (substr($term,0,strlen($prefix))===$prefix)
                $terms[substr($term, strpos($term, '=')+1)]=true;// . '('.$it->get_weight().')';
            $it->next();
        }

        return array_keys($terms);
    }


    private function loadDocument()
    {
        // R�initialise tous les champs � leur valeur par d�faut
        // Corrige � la fois :
        // bug de actionReindex() qui fusionne les notices
        // bug trouv� par SF : search(texte officiel) -> on r�p�te les infos
        // Voir si on peut corriger le bug autrement qu'en bouclant.
        foreach($this->fields as $name=>&$value)
            $value=null;

        if (is_null($this->xapianMSetIterator))
            throw new Exception('Pas de document courant');

        if ($this->xapianMSetIterator->equals($this->xapianMSet->end()))
        {
            $this->xapianDocument=null;
        }
        else
        {
            $this->xapianDocument=$this->xapianMSetIterator->get_document();
            $data=unserialize($this->xapianDocument->get_data());
            foreach($data as $id=>$data)
            {
                if (array_key_exists($id, $this->fieldById))
                    $this->fieldById[$id]=$data;
                // else : il s'agit d'un champ qui n'existe plus
            }
        }
    }

    /**
     * Retourne la liste des termes du document en cours
     *
     * @return array
     */
    public function getTerms()
    {
        if (is_null($this->xapianDocument))
            throw new Exception('Pas de document courant');

//        $indexName=array_flip($this->schema['index']);
//        $entryName=array_flip($this->schema['entries']);

        $result=array();

        $begin=$this->xapianDocument->termlist_begin();
        $end=$this->xapianDocument->termlist_end();
        while (!$begin->equals($end))
        {
            $term=$begin->get_term();
            if (false === $pt=strpos($term,':'))
            {
                $kind='index';
                $index='*';
            }
            else
            {
                //print_r($this->lookuptableById);
                //die();
                $prefix=substr($term,0,$pt);
                if($prefix[0]==='T')
                {
                    $kind='lookup';
                    $prefix=substr($prefix, 1);
                    $index=$this->lookuptableById[$prefix]->name; //$entryName[$prefix];
                }
                else
                {
                    $kind='index';
//                    $index=$prefix; //$indexName[$prefix];
                    $index=$this->indexById[$prefix]->name;
                }
                $term=substr($term,$pt+1);
            }

            $posBegin=$begin->positionlist_begin();
            $posEnd=$begin->positionlist_end();
            $pos=array();
            while(! $posBegin->equals($posEnd))
            {
                $pos[]=$posBegin->get_termpos();
                $posBegin->next();
            }
//            echo "kind=$kind, index=$index, term=$term<br />";
            $result[$kind][$index][$term]=array
            (
                'freq'=>$begin->get_termfreq(),
                'wdf'=>$begin->get_wdf(),
//                'positions'=>$pos
            );
            if ($pos)
                $result[$kind][$index][$term]['positions']=$pos;

            //'freq='.$begin->get_termfreq(). ', wdf='. $begin->get_wdf();
            $begin->next();
        }
        foreach($result as &$t)
            ksort($t);
        return $result;
    }

    /**
     * Retourne une estimation du nombre de r�ponses obtenues lors de la
     * derni�re recherche ex�cut�e.
     *
     * @param int|string $countType le type d'estimation � fournir ou le
     * libell� � utiliser

     * @return int|string
     */
    public function count($countType=0)
    {
        // Si l'argument est une chaine, on consid�re que l'utilisateur veut
        // une �valuation (arrondie) du nombre de r�ponses et cette chaine
        // est le libell� � utiliser (par exemple : 'environ %d ')
        if (is_string($countType))
        {
            if (is_null($this->xapianMSet)) return 0;
            $count=$this->xapianMSet->get_matches_estimated();
            if ($count===0) return 0;

            $min=$this->xapianMSet->get_matches_lower_bound();
            $max=$this->xapianMSet->get_matches_upper_bound();

//            echo
//                'Etapes du calcul : <br />',
//                'min : ', $min, '<br />',
//                'max : ', $max, '<br />',
//                'count : ', $count, '<br />';

            // Si min==max, c'est qu'on a le nombre exact de r�ponses, pas d'�valuation
            if ($min === $max) return $min;

            $unit = pow(10, floor(log10($max-$min))-1);
            $round=max(1,round($count / $unit)) * $unit;

//            echo
//                'diff=', $max-$min, '<br />',
//                'log10(diff)=', log10($max-$min), '<br />',
//                'floor(log10(diff))=', floor(log10($max-$min)), '<br />',
//                'unit -1 =pow(10, floor(log10($max-$min))-1)', $unit, '<br />',
//                'unit=pow(10,floor(log10(diff)))=', pow(10,floor(log10($max-$min))), '<br />',
//                'count/puissance=', $count/$unit, '<br />',
//                'round(count/puissance)=', round($count/$unit), '<br />',
//                'round(count/puissance)* puissance=', $round, '<br />',
//                '<strong>Result : ', $round, '</strong><br />',
//                '<br />'
//                ;

            // Dans certains cas, on peut se retrouver avec une �valuation
            // inf�rieure � start, ce qui g�n�re un pager de la forme
            // "R�ponses 2461 � 2470 sur environ 2000" !
            // Quand on d�tecte ce cas, passe � l'unit� sup�rieure.
            // Cas trouv� avec la requ�te "prise en +charge du +patient diab�tique"
            // dans la base documentaire bdsp.
            if ($round < $this->options->start)
                $round=max(1,round($count / $unit)+1) * $unit;

            $round = number_format($round, 0, '.', '�');

            if ($unit===0.1)
                return '~&#160;' . $round; //  ou '�&#160;'

            $result=
                (strpos($countType, '%')===false)
                ?
                $countType . $round
                :
                sprintf(str_replace('%d', '%s', $countType), $round);

            if ($this->options->checkatleast !== -1)
            {
                $result=sprintf
                (
                    '<a href="%s" title="%s">%s</a>',
                    Routing::buildQueryString(Runtime::$request->copy()->set('_checkatleast', -1)->getParameters(), true),
                    'Obtenir le nombre exact',
                    $result
                );
            }
            return $result;
        }
        return $this->count;
    }

    // *************************************************************************
    // *************** INFORMATIONS SUR LA RECHERCHE EN COURS ******************
    // *************************************************************************

    public function searchInfo($what, $arg=null)
    {
        $what=strtolower($what);

        //if ($this->options && $this->options->has($what)) return $this->options->get($what);

        switch ($what)
        {
            case 'explainquery': return $this->explainQuery($this->xapianQuery);
            case 'query' : return $this->query;
            case 'options': return $this->options->getParameters();
            case 'docid': return $this->xapianMSetIterator->get_docid();

            case 'equation': return $this->query ? $this->query->getEquation() : '';
            case 'rank': return $this->xapianMSetIterator->get_rank()+1;

            case 'correctedequation':
                // Si c'est le premier appel, corrige l'�quation en cours
                if (is_null($this->correctedEquation))
                    $this->spellcheckEquation('^^^%s$$$');

                // Coupe le format indiqu� en "avant"/"apr�s"
                $delim=explode('%s', $arg, 2);
                if (! isset($delim[1])) $delim[1]='';

                // Ins�re les d�limiteurs demand�s dans l'�quation
                return strtr
                (
                    $this->correctedEquation,
                    array
                    (
                        '^^^'=>$delim[0],
                        '$$$'=>$delim[1],
                    )
                );

            // Liste des mots-vides ignor�s dans l'�quation de recherche
            case 'stopwords': return $this->getRequestStopwords(false);
            case 'internalstopwords': return $this->getRequestStopwords(true);

            // Liste des termes pr�sents dans l'�quation + termes correspondants au troncatures
            case 'queryterms': return $this->getQueryTerms(false);
            case 'internalqueryterms': return $this->getQueryTerms(true);

            // Liste des termes du document en cours qui collent � la requ�te
            case 'matchingterms': return $this->getMatchingTerms(false);
            case 'internalmatchingterms': return $this->getMatchingTerms(true);

            // Score obtenu par le document en cours
            case 'score': return $this->xapianMSetIterator->get_percent();
            case 'internalscore': return $this->xapianMSetIterator->get_weight();

            // Tests
            case 'maxpossibleweight': return $this->xapianMSet->get_max_possible();
            case 'maxattainedweight': return $this->xapianMSet->get_max_attained();

            case 'internalquery': return $this->xapianQuery->get_description();
//            case 'internalfilter': return is_null($this->xapianFilter) ? null : $this->xapianFilter->get_description();
            case 'internalfinalquery': return $this->xapianEnquire->get_query()->get_description();

            // Le libell� de la cl� de tri en cours
            case 'sortorder':
                return  $this->sortOrder;

            // La valeur de la cl� de tri pour l'enreg en cours
            case 'sortkey':
                //return $this->sortKey;
                if (empty($this->sortKey)) return array($this->xapianMSetIterator->get_weight());

                $result=array();
                foreach($this->sortKey as $key=>$id)
                    $result[$key]=$this->xapianDocument->get_value($id);
                return $result;

            case 'spydocumentsseen':
                return $this->spy ? $this->spy->get_documents_seen() : 0;

            case 'spytermsseen':
                return $this->spy ? $this->spy->get_terms_seen() : 0;

            default:
                if ($this->options && $this->options->has($what)) return $this->options->get($what);
                return null;
        }
    }

    /**
     * Retourne la liste des termes de recherche g�n�r�s par la requ�te.
     *
     * getQueryTerms construit la liste des termes d'index qui ont �t� g�n�r�s
     * par la derni�re requ�te analys�e.
     *
     * La liste comprend tous les termes pr�sents dans la requ�te (mais pas les
     * mots vides) et tous les termes g�n�r�s par les troncatures.
     *
     * Par exemple, la requ�te <code>�duc* pour la sant�</code> pourrait
     * retourner <code>array('educateur', 'education', 'sante')</code>.
     *
     * Par d�faut, les termes retourn�s sont filtr�s de mani�re � pouvoir �tre
     * pr�sent�s � l'utilisateur (d�doublonnage des termes, suppression des
     * pr�fixes internes utilis�s dans les index de xapian), mais vous pouvez
     * passer <code>false</code> en param�tre pour obtenir la liste brute.
     *
     * @param bool $internal flag indiquant s'il faut filtrer ou non la liste
     * des termes.
     *
     * @return array un tableau contenant la liste des termes obtenus.
     */
    private function getQueryTerms($internal=false)
    {
        $terms=array();

        // Si aucune requ�te n'a �t� ex�cut�e, retourne un tableau vide
        if (is_null($this->xapianQuery)) return $terms;

        // R�cup�re tous les termes pr�sents dans la requ�te en cours
        $begin=$this->xapianQuery->get_terms_begin();
        $end=$this->xapianQuery->get_terms_end();
        while (!$begin->equals($end))
        {
            $term=$begin->get_term();
            if ($internal)
            {
                $terms[]=$term;
            }
            else
            {
                // Supprime le pr�fixe �ventuel
                if (false !== $pt=strpos($term, ':')) $term=substr($term,$pt+1);

                // Pour les articles, supprime les underscores
                $term=strtr(trim($term, '_'), '_', ' ');

                $terms[$term]=true;
            }

            $begin->next();
        }
        return $internal ? $terms : array_keys($terms);
    }

    /**
     * Retourne la liste des mots-vides pr�sents dans la la requ�te.
     *
     * getRequestStopWords construit la liste des termes qui figuraient dans
     * la derni�re requ�te analys�e mais qui ont �t� ignor�s parcequ'ils
     * figuraient dans la liste des mots-vides d�inis dans la base.
     *
     * Par exemple, la requ�te <code>outil pour le web, pour internet</code>
     * pourrait retourner <code>array('pour', 'le')</code>.
     *
     * Par d�faut, les termes retourn�s sont d�doublonn�s, mais vous pouvez
     * passer <code>false</code> en param�tre pour obtenir la liste brute (dans
     * l'exemple ci-dessus, on obtiendrait <code>array('pour', 'le', 'pour')</code>
     *
     * @param bool $internal flag indiquant s'il faut d�doublonner ou non la
     * liste des mots-vides.
     *
     * @return array un tableau contenant la liste des termes obtenus.
     */
    private function getRequestStopWords($internal=false)
    {
        // Liste des mots vides ignor�s
        if (is_null($this->xapianQueryParser)) return array();

        $stopwords=array();
        $iterator=$this->xapianQueryParser->stoplist_begin();
        while(! $iterator->equals($this->xapianQueryParser->stoplist_end()))
        {
            if ($internal)
                $stopwords[]=$iterator->get_term(); // pas de d�doublonnage
            else
                $stopwords[$iterator->get_term()]=true; // d�doublonne en m�me temps
            $iterator->next();
        }
        return $internal ? $stopwords : array_keys($stopwords);
    }

    /**
     * Retourne la liste des termes du document en cours qui correspondent aux
     * terms de recherche g�n�r�s par la requ�te.
     *
     * getMatchingTerms construit l'intersection entre la liste des termes
     * du document en cours et la liste des termes g�n�r�s par la requ�te.
     *
     * Cela permet, entre autres, de comprendre pourquoi un document appara�t
     * dans la liste des r�ponses.
     *
     * Par d�faut, les termes retourn�s sont filtr�s de mani�re � pouvoir �tre
     * pr�sent�s � l'utilisateur (d�doublonnage des termes, suppression des
     * pr�fixes internes utilis�s dans les index de xapian), mais vous pouvez
     * passer <code>false</code> en param�tre pour obtenir la liste brute.
     *
     * @param bool $internal flag indiquant s'il faut filtrer ou non la liste
     * des termes.
     *
     * @return array un tableau contenant la liste des termes obtenus.
     */
    private function getMatchingTerms($internal=false)
    {
        $terms=array();
        $begin=$this->xapianEnquire->get_matching_terms_begin($this->xapianMSetIterator);
        $end=$this->xapianEnquire->get_matching_terms_end($this->xapianMSetIterator);
        while(!$begin->equals($end))
        {
            $term=$begin->get_term();
            if ($internal)
            {
                $terms[]=$term;
            }
            else
            {
                // Supprime le pr�fixe �ventuel
                if (false !== $pt=strpos($term, ':')) $term=substr($term,$pt+1);

                // Pour les articles, supprime les underscores
                $term=strtr(trim($term, '_'), '_', ' ');

                $terms[$term]=true;
            }

            $begin->next();
        }
        return $internal ? $terms : array_keys($terms);
    }

    public function moveNext()
    {
        if (is_null($this->xapianMSet)) return;
        $this->xapianMSetIterator->next();
        $this->loadDocument();
        $this->eof=$this->xapianMSetIterator->equals($this->xapianMSet->end());
    }

    const
        MAX_KEY=240,            // Longueur maximale d'un terme, tout compris (doit �tre inf�rieur � BTREE_MAX_KEY_LEN de xapian)
        MAX_PREFIX=4,           // longueur maxi d'un pr�fixe (par exemple 'T99:')
        MIN_TERM=1,             // Longueur minimale d'un terme
        MAX_TERM=236,           // =MAX_KEY-MAX_PREFIX, longueur maximale d'un terme
        MIN_ENTRY_SLOT=2,       // longueur minimale d'un mot de base dans une table de lookup
        MAX_ENTRY_SLOT=20,      // longueur maximale d'un mot de base dans une table de lookup
        MAX_ENTRY=219           // =MAX_KEY-MAX_ENTRY_SLOT-1, longueur maximale d'une valeur dans une table des entr�es (e.g. masson:Editions Masson)
        ;

    /**
     * Sugg�re � l'utilisateur des entr�es ou des termes existant dans l'index
     * de xapian.
     *
     * Lookup prend en param�tre un terme ou une expression et recherche dans les
     * index de xapian toutes les entr�es qui commencent par cette valeur.
     *
     * Lookup teste dans l'ordre si la "table" indiqu�e en param�tre correspond
     * au nom d'une table de lookup, d'un alias ou d'un index existant (une
     * exception sera g�n�r�e si ce n'est pas le cas).
     *
     * Selon la source utilis�e, la nature des suggestions retourn�es sera
     * diff�rente :
     * - S'il s'agit d'une table de lookup, lookup retourne toutes les entr�es (en format riche)
     *   qui commencent par l'expression de recherche indiqu�e.
     * - S'il s'agit d'un index de type "article", lookup retourne toutes les entr�es (en format
     *   "pauvre", en minuscules non accentu�es) qui commencent par l'expression recherch�e.
     * - S'il s'agit d'un index de type "mot", seul le dernier mot indiqu� dans l'expression de
     *   recherche est pris en compte. Lokkup retourne alors tous les mots de l'index (en format
     *   pauvre) qui commencent par le dernier des mots indiqu�s dans l'expression recherch�e.
     * - S'il s'agit d'un alias, les suggestions retourn�es correspondront au type des index
     *   composant cet alias (i.e. soit des articles, soit des termes).
     *
     * @param string $table le nom de la table de lookup, de l'alias ou de
     * l'index � utiliser pour g�n�rer des suggestions.
     *
     * @param string $term le mot ou l'expression recherch�e.
     *
     * @param int $max le nombre maximum de suggestions � retourner (0=pas de limite)
     *
     * @param string $format d�finit le format � utiliser pour la mise en
     * surbrillance des termes de recherche de l'utilisateur au sein de chacun
     * des suggestions trouv�es.
     *
     * Il s'agit d'une chaine qui sera appliqu�e aux suggestions trouv�es en utilisant
     * la fonction sprintf() de php (exemple de format : <strong>%s</strong>).
     *
     * Si $format est null ou s'il s'agit d'une chaine vide, aucune surbrillance
     * ne sera appliqu�e.
     *
     * @return array un tableau contenant les suggestions obtenues. Chaque cl�
     * du tableau contient une suggestion et la valeur associ�e contient le
     * nombre d'occurences de cette entr�e dans la base.
     *
     * Exemple :
     * <code>
     * array
     * (
     *     'droit du malade' => 10,
     *     'information du malade' => 3
     * )
     * </code>
     *
     * Les suggestions retourn�es sont tri�es par ordre alphab�tique croissant.
     */
    public function lookup($table, $value, $max=10, $format='<strong>%s</strong>')
    {
        require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR. 'XapianLookupHelpers.php');

        /**
         * @var LookupHelper
         */
        $helper=null;

		// Construit la version "minuscules non accentu�es" du nom de la table indiqu�e
        $key=Utils::ConvertString($table, 'alphanum');

        // Teste s'il s'agit d'une table de lookup
        if (isset($this->schema->lookuptables[$key]))
        {
            $helper=new SimpleTableLookup();
            $prefix='T' . $this->schema->lookuptables[$key]->_id . ':';
        }

        // Teste s'il s'agit d'un alias
        elseif (isset($this->schema->aliases[$key]))
        {
            $helper=new AliasLookup();
            $prefix='';
            foreach($this->schema->aliases[$key]->indices as $name=>$index)
            {
                // S'il existe une table de lookup avec ce nom, on l'utilise plut�t que l'index
                if (isset($this->schema->lookuptables[$name]))
                {
                    $item=new SimpleTableLookup();
                	$prefix='T' . $this->schema->lookuptables[$name]->_id . ':';
                }

                // C'est un index. D�termine si c'est un index au mot ou � l'article
                else
                {
                    $index=$this->schema->indices[$name];
                    if (reset($index->fields)->values)
                        $item=new ValueLookup();
                    else
                        $item=new TermLookup();
                    $prefix=$index->_id . ':';
                }

                // Initialise l'item et l'ajoute � l'alias
                $item->setIterators($this->xapianDatabase->allterms_begin(), $this->xapianDatabase->allterms_end());
                $item->setPrefix($prefix);

                $helper->add($item);
            }

            $prefix=''; // AliasLookup n'utilise pas les pr�fixes
        }

        // Teste s'il s'agit d'un index
        elseif (isset($this->schema->indices[$key]))
        {
            // Teste s'il s'agit d'un index "� l'article"

            // Remarque : on ne peut pas tester directement l'index, car chacun des
            // champs peut �tre index� � l'article ou au mot. Du coup, on teste
            // uniquement le type d'indexation du premier champ et on suppose que
            // les autres champs de l'index sont index�s pareil.

            if (reset($this->schema->indices[$key]->fields)->values)
                $helper=new ValueLookup();
            else
                $helper=new TermLookup();

            $prefix=$this->schema->indices[$key]->_id . ':';
        }

        // Impossible de faire un lookup
        else
        {
            throw new Exception("Impossible de faire un lookup sur '$table' : ce n'est ni une table de lookup, ni un alias, ni un index");
        }

        // Param�tre le helper
        $helper->setIterators($this->xapianDatabase->allterms_begin(), $this->xapianDatabase->allterms_end());
        $helper->setPrefix($prefix);

        // Fait le lookup et retourne les r�sultats
        return $helper->lookup($value, $max, $format);
    }


    /**
     * Recherche les tokens de la base qui commencent par le terme indiqu�.
     *
     * Cette m�thode est similaire � lookup, mais recherche parmi les termes
     * d'indexation et non pas parmi les tables de lookup.
     *
     * @param string $term le terme recherch�
     *
     * @param int $max le nombre maximum de valeurs � retourner (0=pas de limite)
     *
     * @param int $sort l'ordre de tri souhait� pour les r�ponses :
     *   - 0 : trie les r�ponses par nombre d�croissant d'occurences dans la base (valeur par d�faut)
     *   - 1 : trie les r�ponses par ordre alphab�tique croissant
     *
     * @return array
     */
    public function lookupTerm($term, $max=0, $sort=0)
    {
        static $charFroms=
            "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe\xff";

        static $charTo=
            '                                                0123456789      @abcdefghijklmnopqrstuvwxyz      abcdefghijklmnopqrstuvwxyz                                                                     aaaaaaaceeeeiiiidnooooo 0uuuuy saaaaaaaceeeeiiiidnooooo  uuuuyby';

        $begin=$this->xapianDatabase->allterms_begin();
        $end=$this->xapianDatabase->allterms_end();

        $count=0;
        if ($max<=0) $max=PHP_INT_MAX;

        $result=array();

        $term=strtr(trim($term), $charFroms, $charTo);
        if (false === $token=strtok($term, ' '))
            $token=''; // terme vide : retourne les n premiers de la liste

        $nb=0;
        while($token !== false)
        {
            $start=$token;
            $begin->skip_to($start);

            while (!$begin->equals($end))
            {
                $entry=$begin->get_term();

                if ($start !== substr($entry, 0, strlen($start)))
                    break;

                if (!isset($result[$entry]))
                {
                    $result[$entry]=$begin->get_termfreq();
                    ++$nb;
                    if ( ($sort && $nb >= $max) or (! $sort && $nb>=1000))
                        break;
                }

                $begin->next();
            }
            $token=strtok(' ');
        }

        // Trie des r�ponses
        if ($sort===0)
        {
            arsort($result, SORT_NUMERIC);
            $result=array_slice($result,0,$max);
        }

        return $result;
    }

    public function dumpTerms($start='', $max=0)
        {
        $begin=$this->xapianDatabase->allterms_begin();
        $end=$this->xapianDatabase->allterms_end();
        $begin->skip_to($start);
        $count=0;
        if ($max<=0) $max=PHP_INT_MAX;
        while (!$begin->equals($end))
        {
            if ($count >= $max) break;
            $term=$begin->get_term();
            if (substr($term, 0, strlen($start))!=$start) break;
//            if(strlen($term)=='' || $term[0]!=='T' && trim(strtr($term, 'abcdefghijklmnopqrstuvwxyz0123456789_:', '                                      '))!=='')
//            {
                echo '<li>[', $term, '], len=', strlen($term), ', freq=', $begin->get_termfreq(), '</li>', "\n";
                $count++;
//            }
            $begin->next();
        }
        echo '<strong>', $count, ' termes</strong>';
    }

    public function totalCount()
    {
        return $this->xapianDatabase->get_doccount();
    }
    public function lastDocId()
    {
        return $this->xapianDatabase->get_lastdocid();
    }
    public function averageLength()
    {
        return $this->xapianDatabase->get_avlength();
    }

    public function deleteAllRecords()
    {
        $enquire=new XapianEnquire($this->xapianDatabase);
        $enquire->set_query(new XapianQuery(''));
        $enquire->set_docid_Order(XapianEnquire::ASCENDING);

        $mset=$enquire->get_MSet(0, -1);

        $i=0;
        $iterator=$mset->begin();
        while (! $iterator->equals($mset->end()))
        {
            $id=$iterator->get_docid();
            $this->xapianDatabase->delete_document($iterator->get_docid());
            if (debug) echo ++$i, ': doc ', $id, ' supprim�<br />';
            $iterator->next();
        }
    }

    public function reindex()
    {
        $startTime=microtime(true);

        // V�rifie que la base est ouverte en �criture
        if (! $this->xapianDatabase instanceof XapianWritableDatabase)
            throw new Exception('Impossible de r�indexer une base de donn�es ouverte en lecture seule.');

        // M�morise le path actuel de la base
        $path=$this->getPath();

        echo '<h1>R�indexation compl�te de la base ', basename($path), '</h1>';

        // S�lectionne toutes les notices
        $this->search('*', array('sort'=>'+', 'max'=>-1));
        $count=$this->count();
        if ($count==0)
        {
            echo '<p>La base ne contient aucun document, il est inutile de lancer la r�indexation.</p>';
            return;
        }
        echo '<ol>';

        echo '<li>La base contient ', $count, ' notices.</li>';

        // Si une base 'tmp' existe d�j�, on le signale et on s'arr�te
        echo '<li>Cr�ation de la base de donn�es temporaire...</li>';
        $pathTmp=$path.DIRECTORY_SEPARATOR.'tmp';
        if (file_exists($pathTmp) && count(glob($pathTmp . DIRECTORY_SEPARATOR . '*'))!==0)
            throw new Exception("Le r�pertoire $pathTmp contient d�j� des donn�es (r�indexation pr�c�dente interrompue ?). Examinez et videz ce r�pertoire puis relancez la r�indexation.");

        // Cr�e la nouvelle base dans './tmp'
        $tmp=Database::create($pathTmp, $this->getSchema(), 'xapian');

        // Cr�e le r�pertoire 'old' s'il n'existe pas d�j�
        $pathOld=$path.DIRECTORY_SEPARATOR.'old';
        if (! is_dir($pathOld))
        {
            if (! @mkdir($pathOld))
                throw new Exception('Impossible de cr�er le r�pertoire ' . $pathOld);
        }

        // Donn�es collect�es pour le graphique
        $width=560;
        $data=array();
        $step=ceil($this->count() / ($width*1/4)); // on prendra une mesure toute les step notices

        // Recopie les notices
        echo '<li>R�indexation des notices...</li>';
        $last=$start=microtime(true);
        $i=0;
        foreach ($this as $record)
        {
            $tmp->addRecord();
            foreach($record as $field=>$value)
            {
                $tmp[$field]=$value;
            }
            $tmp->saveRecord();

            $id=$this->xapianMSetIterator->get_docid();
            $time=microtime(true);
            if (($time-$start)>1)
            {
                TaskManager::progress($i, $count);
                $start=$time;
            }

            if (0 === $i % $step)
            {
                if ($i>2)
                {
                    $data[$i]=round($step/($time-$last),0);
                }
                $last=$time;
            }

            $i++;
        }
        TaskManager::progress($i, $count);

        // + copier les spellings, les synonyms, les meta keys

        // Ferme la base temporaire
        echo '<li>Flush et fermeture de la base temporaire...</li>';
        $tmp=null;

        if (($i % $step)>0) $data[$i]=round(($i%$step)/(microtime(true)-$last),0);

        // Ferme la base actuelle en mettant � 'null' toutes les propri�t�s de $this
        echo '<li>Fermeture de la base actuelle...</li>';

        $me=new ReflectionObject($this);
        foreach((array)$me->getProperties() as $prop)
        {
            $prop=$prop->name;
            $this->$prop=null;
        }

        /*
            On va maintenant remplacer les fichiers de la base existante par
            les fichiers de la base temporaire.

            Potentiellement, il se peut que quelqu'un essaie d'ouvrir la base
            entre le moment o� on commence le transfert et le moment o� tout
            est transf�r�.

            Pour �viter �a, on proc�de en deux �tapes :
            1. on d�place vers le r�pertoire ./old tous les fichiers de la base
               existante, en commen�ant par le fichier de version (iamflint), ce
               qui fait que plus personne ne peut ouvrir la base d�s que
               celui-ci a �t� renomm� ;
            2. on transf�re tous les fichier de ./tmp en ordre inverse,
               c'est-�-dire en terminant par le fichier de version, ce qui fait
               que personne ne peut ouvrir la base tant qu'on n'a pas fini.
        */

        // Liste des fichiers pouvant �tre cr��s pour une base flint
        $files=array
        (
            // les fichiers de version doivent �tre en premier
            'iamflint',
            'iamchert',
            'uuid', // replication stuff

            // autres fichiers
            'flintlock',

            'position.baseA',
            'position.baseB',
            'position.DB',

            'postlist.baseA',
            'postlist.baseB',
            'postlist.DB',

            'record.baseA',
            'record.baseB',
            'record.DB',

            'spelling.baseA',
            'spelling.baseB',
            'spelling.DB',

            'synonym.baseA',
            'synonym.baseB',
            'synonym.DB',

            'termlist.baseA',
            'termlist.baseB',
            'termlist.DB',

            'value.baseA',
            'value.baseB',
            'value.DB',
        );


        // Transf�re tous les fichiers existants vers le r�pertoire ./old
        clearstatcache();
        echo '<li>Transfert de la base actuelle dans le r�pertoire "old"...</li>';
        foreach($files as $file)
        {
            $old=$pathOld . DIRECTORY_SEPARATOR . $file;
            if (file_exists($old))
            {
                unlink($old);
            }

            $h=$path . DIRECTORY_SEPARATOR . $file;
            if (file_exists($h))
            {
                rename($h, $old);
            }
        }

        // Transf�re les fichiers du r�pertoire tmp dans le r�pertoire de la base
        echo '<li>Installation de la base temporaire comme base actuelle...</li>';
        foreach(array_reverse($files) as $file)
        {
            $h=$path . DIRECTORY_SEPARATOR . $file;

            $tmp=$pathTmp . DIRECTORY_SEPARATOR . $file;
            if (file_exists($tmp))
            {
                //echo "D�placement de $tmp vers $h<br />";
                rename($tmp, $h);
            }
        }

        // Essaie de supprimer le r�pertoire tmp (d�sormais vide)
        $files=glob($pathTmp . DIRECTORY_SEPARATOR . '*');
        if (count($files)!==0)
            echo '<li><strong>Warning : il reste des fichiers dans le r�pertoire tmp</strong></li>';

        // todo: en fait on n'arrive jamais � supprimer tmp. xapian garde un handle dessus ? � voir, pas indispensable de supprimer tmp
        /*
            if (!@unlink($pathTmp))
                echo '<p>Warning : impossible de supprimer ', $pathTmp, '</p>';
        */

        // R�ouvre la base
        echo '<li>R�-ouverture de la base...</li>';
        $this->doOpen($path, false);
        $this->search('*', array('sort'=>'+', 'max'=>-1));

        echo '<li>La r�indexation est termin�e.</li>';
        echo '<li>Statistiques :';

        // G�n�re un graphique
        $type='lc';        // type de graphe
        $size=$width.'x300';    // Taille du graphe (largeur x hauteur)
        $title=utf8_encode('Nombre de notices r�index�es par seconde');
        $grid='5,5,1,5';  // largeur, hauteur, taille trait, taille blanc
        $xrange=min(array_keys($data)) . ',' . max(array_keys($data));

        $min=min($data);
        $max=max($data);
        $average=array_sum($data)/count($data);
        $yrange=$min . ',' . $max;

        $ratio=($max-$min)/100;
        foreach($data as &$val)
            $val=round(($val-$min)/$ratio, 0);

        $data='t:' . implode(',',$data);

        $avg01=($average-$min)/($max-$min);
        $src=sprintf
        (
            'http://chart.apis.google.com/chart?cht=%s&chs=%s&chd=%s&chtt=%s&chg=%s&chxt=x,y,x&chxr=0,%s|1,%s&chxl=2:||taille de la base&chm=r,220000,0,%.3F,%.3F',
            $type,
            $size,
            $data,
            $title,
            $grid,
            $xrange,
            $yrange,
            $avg01,
            $avg01-0.001
        );

        echo '<p><img style="border: 4px solid black; background-color: #fff; padding: 1em; margin: auto;" src="'.$src.'" /></p>';
        echo sprintf('<p>Minimum : %d notices/seconde, Maximum : %d notices/seconde, Moyenne : %.3F notices/seconde', $min, $max, $average);
        echo '<p>Dur�e totale de la r�indexation : ', Utils::friendlyElapsedTime(microtime(true)-$startTime), '.</p>';

        echo '</li></ol>';
    }

/*
    public function reindex()
    {
        while(ob_get_level()) ob_end_flush();
        $this->search(null, array('_sort'=>'+', '_max'=>40000));
        echo $this->count(), ' notices � r�indexer. <br />';

        $start=microtime(true);
        $i=0;
        foreach ($this as $record)
        {
            $id=$this->xapianMSetIterator->get_docid();
            if (0 === $i % 1000)
            {
                echo sprintf('%.2f', microtime(true)-$start), ', i=', $i, ', id=', $id, '<br />';
                flush();
            }

            $this->xapianDocument->clear_terms();
            $this->xapianDocument->clear_values();

            // Remplace le document existant
            $this->xapianDatabase->replace_document($id, $this->xapianDocument);

            $i++;
        }

        //
        echo sprintf('%.2f', microtime(true)-$start), ', i=', $i, ', id=', $id, ', flush de la base...<br />';
        flush();

        $this->xapianDatabase->flush();
        echo sprintf('%.2f', microtime(true)-$start), ', termin� !<br />';
        flush();
    }

    public function reindexOld()
    {
        while(ob_get_level()) ob_end_flush();
        $this->search(null, array('_sort'=>'+', '_max'=>20000));
        echo $this->count(), ' notices � r�indexer. <br />';

        $start=microtime(true);
//        $this->xapianDatabase->begin_transaction(false);
        $i=0;
        foreach ($this as $record)
        {
            $id=$this->xapianMSetIterator->get_docid();
            if (0 === $i % 1000)
            {
                echo sprintf('%.2f', microtime(true)-$start), ', i=', $i, ', id=', $id, '<br />';
                flush();
            }

            // R�-indexe l'enregistrement
            $this->initializeDocument();

            // Remplace le document existant
            $this->xapianDatabase->replace_document($id, $this->xapianDocument);

            $i++;
        }
//        $this->xapianDatabase->commit_transaction();

        //
        echo sprintf('%.2f', microtime(true)-$start), ', i=', $i, ', id=', $id, ', flush de la base...<br />';
        flush();

        $this->xapianDatabase->flush();
        echo sprintf('%.2f', microtime(true)-$start), ', termin� !<br />';
        flush();
    }
*/

    public function warmUp()
    {
        $begin=$this->xapianDatabase->allterms_begin();
        $end=$this->xapianDatabase->allterms_end();
//        echo 'Premier terme : ', $term=$begin->get_term(), '<br />';
//        die($term);
//        $begin->skip_to('zzzzzz');
//        echo $begin->get_description();
//        //echo 'Premier terme : ', $begin->get_term(), '<br />';
//        //echo 'Dernier terme : ', $end->get_term(), '<br />';
//        die('here');
//         return;
        while (!$begin->equals($end))
        {
            $term=$begin->get_term();
            echo $term, '<br />';
            $this->search($term);
            echo $this->count(), '<br />';
            $term[0]=chr(1+ord($term[0]));
            $begin->skip_to($term);
        }
    }
}

/**
 * Repr�sente un enregistrement dans une base {@link XapianDatabase}
 *
 * @package     fab
 * @subpackage  database
 */
class XapianDatabaseRecord extends DatabaseRecord
{
    /**
     * @var Array Liste des champs de cet enregistrement
     */
    private $fields=null;

    private $schema=null;

    /**
     * Lors d'un parcours s�quentiel, num�ro de l'�l�ment de tableau
     * en cours. Utilis� par {@link next()} pour savoir si on a atteint
     * la fin du tableau.
     *
     * @var unknown_type
     */
    private $current=0;

    /**
     * {@inheritdoc}
     *
     * @param Array $fields la liste des champs de la base
     */
    public function __construct(& $fields, DatabaseSchema $schema)
    {
        $this->fields= & $fields;
        $this->schema= $schema;
    }

    /* <ArrayAccess> */

    public function offsetSet($offset, $value)
    {

        // Version minu non accentu�e du nom du champ
        $key=Utils::ConvertString($offset, 'alphanum');
        $this->fields[$key]=$value;
        return;
        // V�rifie que le champ existe
        if (! array_key_exists($key, $this->fields))
            throw new DatabaseFieldNotFoundException($offset);

        // V�rifie que la valeur concorde avec le type du champ
        switch ($this->schema->fields[$key]->_type)
        {
            case DatabaseSchema::FIELD_AUTONUMBER:
            case DatabaseSchema::FIELD_INT:
                /*
                 * Valeurs stock�es telles quelles :
                 *      null -> null
                 *      12 -> 12
                 * Valeurs converties : (par commodit�, exemple, import fichier texte)
                 *      '' -> null
                 *      '12' -> 12
                 * Erreurs :
                 *      '12abc' -> exception            pas de tol�rance si on caste une chaine
                 *      true, false -> exception        un boole�en n'est pas un entier
                 *      autres -> exception
                 */
                if (is_null($value) || is_int($value)) break;
                if (is_string($value) && ctype_digit($value))
                {
                    $value=(int)$value;
                    break;
                }
                if ($value==='')
                {
                    $value=null;
                    break;
                }
                throw new DatabaseFieldTypeMismatch($offset, $this->schema->fields[$key]->type, $value);

            case DatabaseSchema::FIELD_BOOL:
                /*
                 * Valeurs stock�es telles quelles :
                 *      null -> null
                 *      true -> true
                 *      false -> false
                 * Valeurs converties : (par commodit�, exemple, import fichier texte)
                 *      0 -> false
                 *      1 -> true
                 *      '1','true','vrai','on' -> true
                 *      '','0','false','faux','off' -> false
                 * Erreurs :
                 *      'xxx' -> exception       toute autre chaine (y compris vide ou espace) est une erreur
                 *      3, -1-> exception        tout autre entier est une erreur
                 *      autres -> exception
                 */
                if (is_null($value) || is_bool($value)) break;
                if (is_int($value))
                {
                    if ($value===0 | $value===1)
                    {
                        $value=(bool) $value;
                        break;
                    }
                    throw new DatabaseFieldTypeMismatch($offset, $this->schema->fields[$key]->type, $value);
                }
                if (is_string($value))
                {
                    switch(strtolower(trim($value)))
                    {
                        case 'true':
                        case 'vrai':
                        case 'on':
                        case '1':
                            $value=true;
                            break 2;

                        case 'false':
                        case 'faux':
                        case 'off':
                        case '0':
                            $value=false;
                            break 2;
                        case '':
                            $value=null;
                            break 2;
                    }
                }
                throw new DatabaseFieldTypeMismatch($offset, $this->schema->fields[$key]->type, $value);

            case DatabaseSchema::FIELD_TEXT:
                if (is_null($value) || is_string($value)) break;
                if (is_scalar($value))
                {
                    $value=(string)$value;
                    if ($value==='')
                    {
                        $value=null;
                        break;
                    }
                }
                throw new DatabaseFieldTypeMismatch($offset, $this->schema->fields[$key]->type, $value);
                break;
        }

        // Stocke la valeur du champ
        $this->fields[$key]=$value;
    }

    public function offsetGet($offset)
    {
        return $this->fields[Utils::ConvertString($offset, 'alphanum')];
    }

    public function offsetUnset($offset)
    {
        unset($this->fields[Utils::ConvertString($offset, 'alphanum')]);
    }

    public function offsetExists($offset)
    {
        return isset($this->fields[Utils::ConvertString($offset, 'alphanum')]);
    }

    /* </ArrayAccess> */


    /* <Countable> */

    public function count()
    {
        return $this->fields->count;
    }

    /* </Countable> */


    /* <Iterator> */

    public function rewind()
    {
        reset($this->fields);
        $this->current=1;
    }

    public function current()
    {
        return current($this->fields);
    }

    public function key()
    {
        return $this->schema->fields[key($this->fields)]->name;
        /*
         * On ne retourne pas directement key(fields) car sinon on r�cup�re
         * un nom en minu sans accents qui sera ensuite utilis� dans les boucles,
         * les callbacks, etc.
         * Si un callback a le moindre test du style if($name='Aut'), cela ne marchera
         * plus.
         * On fait donc une indirection pour retourner comme cl� le nom exact du
         * champ tel que saisi par l'utilisateur dans le sch�ma.
         */
    }

    public function next()
    {
        next($this->fields);
        $this->current++;
    }

    public function valid()
    {
        return $this->current<=count($this->fields);
    }

    /* </Iterator> */

}

/**
 * Exception g�n�r�e lorsqu'on essaie de modifier une base de donn�es
 * ouverte en lecture seule
 *
 * @package     fab
 * @subpackage  database
 */
class DatabaseReadOnlyException extends LogicException
{
    public function __construct()
    {
        parent::__construct('La base est en ouverte en lecture seule.');
    }
}

/**
 * Exception g�n�r�e lorsqu'on essaie d'acc�der � un enregistrement alors
 * qu'il n'y a aucun enregistrement en cours.
 *
 * @package     fab
 * @subpackage  database
 */
class DatabaseNoRecordException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Pas d\'enregistrement courant.');
    }
}

/**
 * Exception g�n�r�e lorsqu'on essaie d'acc�der � un champ qui n'existe pas
 * dans la base
 *
 * @package     fab
 * @subpackage  database
 */
class DatabaseFieldNotFoundException extends InvalidArgumentException
{
    public function __construct($field)
    {
        parent::__construct(sprintf('Le champ %s n\'existe pas.', $field));
    }
}

/**
 * Exception g�n�r�e par {@link saveRecord()} et {@link cancelUpdate()} si elles
 * sont appell�es alors que l'enregistrement actuel n'est pas en cours de
 * modification.
 *
 * @package     fab
 * @subpackage  database
 */
class DatabaseNotEditingException extends LogicException
{
    public function __construct()
    {
        parent::__construct('L\'enregistrement n\'est pas en cours de modification');
    }
}

/**
 * Exception g�n�r�e lorsqu'on essaie de stocker dans un champ une valeur qui
 * n'est pas compatible avec le type du champ.
 *
 * @package     fab
 * @subpackage  database
 */
class DatabaseFieldTypeMismatch extends RuntimeException
{
    public function __construct($field, $type, $value)
    {
        parent::__construct(sprintf('Valeur incorrecte pour le champ %s (%s) : %s', $field, $type, var_export($value, true)));
    }
}

class UserQuery extends Request
{
    private $schema=null;

    public function __construct(array $parameters=array(), DatabaseSchema $schema=null)
    {
        parent::__construct($parameters);
        $this->schema=$schema;
    }

    public static function create(array $parameters=array(), DatabaseSchema $schema=null)
    {
        return new UserQuery($parameters, $schema);
    }

    public function __toString()
    {
        return $this->getEquation();
    }

    public function getEquation()
    {

/*
(
    +(tous les _equation crois�s en AND)
    tous les champs prob/love/hate concat�n�s avec des espaces
)
AND
(
    tous les champs bool crois�s en defaultOp (occurences crois�es en OR)
)
AND
(
    tous les _filter crois�s en AND
)

 */

        /*
            Par d�faut, la requ�te g�n�r�e va �tre de la forme :
            +(_equation _equation) autres_crit�res

            Quand defaultop===AND, le + de d�but est inutile et il alourdit la lecture
            de l'�quation. Du coup, on ne le g�n�re que si on est en "OU".
         */
        $plus = ($this->get('defaultopcode') === XapianQuery::OP_OR) ? '+' : '';


        $result=array();

        // tous les _equation crois�s en AND
        if ($this->equation)
        {
            $t=array();
            $equations=(array) $this->equation;
            foreach($equations as $value)
            {
                if ($value===null or $value==='' or $value===array()) continue;
                if (count($equations)>1) $this->addBrackets($value);
                $t[]=$value;
            }
            $h=implode(' AND ', $t);
            $result[0] = $h;
        }

        // S�pare les champs Bool des autres
        if ($this->auto)
        {
            // Parcourt tous les param�tres
            $t=$bool=$hate=array();
            foreach($this->auto as $name=>$value)
            {
                if ($value===null or $value==='' or $value===array()) continue;

                // Ne garde que ceux qui correspondent � un nom d'index ou d'alias existant
                $indexName=strtolower(ltrim($name,'+-'));
                if (isset($this->schema->indices[$indexName]))
                    $index=$this->schema->indices[$indexName];
                elseif(isset($this->schema->aliases[$indexName]))
                    $index=$this->schema->aliases[$indexName];
                else
                    continue;

                foreach((array)$value as $value)
                {
                    if (trim($value) === '') continue;

                    $this->addBrackets($value);

                    // D�termine comment il faut analyser la requ�te et o� la stocker
                    switch(substr($name, 0, 1))
                    {
                        case '+':
                            $t[] = $name . '=' . $value;
                            break;

                        case '-':
                            $hate[] = '-' . substr($name,1) . '=' . $value;
                            break;

                        default:
                            if (isset($index->_type) && $index->_type === DatabaseSchema::INDEX_BOOLEAN)
                                $bool[$name][] = $name . '=' . $value;
                            else
                                $t[] = $name . '=' . $value;
                            break;
                    }
                }
            }

            if ($bool)
            {
                foreach($bool as $name=>&$value)
                {
                    $value=implode(' OR ', $value);
                    $this->addBrackets($value);
                }
                $h=implode(' AND ', $bool);
                $result[] = $h;
            }

            if ($t)
            {
                $h = implode(' AND ', $t);
                if (isset($result[0]))
                {
                    $this->addBrackets($result[0]);
                    $result[0] = $plus . $result[0] . ' ' . $h;
                }
                else
                    $result[0] = $h;
            }

            if ($hate)
            {
                $h = implode(' ', $hate);
                if (isset($result[0]))
                {
                    $this->addBrackets($result[0]);
                    $result[0] = '' . $result[0] . ' ' . $h;
                }
                else
                    $result[0] = $h;
            }

        }


        // AND (tous les _filter crois�s en AND)
        if ($this->filter)
        {
            $t2=array();
            foreach((array) $this->filter as $equation)
            {
                $this->addBrackets($equation);
                $t2[]=$equation;
            }
            $h=implode(' AND ', $t2);
            $result[] = $h;
        }

        if (count($result)>1) array_walk($result, array($this, 'addBrackets'));
        $result=implode(' AND ', $result);
        return $result;
    }

    /**
     * Ajoute des parenth�ses autour de l'�quation pass�e au param�tre si c'est
     * n�cessaire.
     *
     * La m�thode consid�re que l'�quation pass�e en param�tre est destin�e �
     * �tre combin�e en "ET" avec d'autres �quations.
     *
     * Dans sa version actuelle, la m�thode supprime de l'�quation les blocs
     * parenth�s�s, les phrases et les articles et ajoute des parenth�ses si
     * ce qui reste contient un ou plusieurs espaces.
     *
     * Id�alement, il faudrait faire un traitement beaucoup plus compliqu�, mais
     * �a revient quasiment � r�-�crire un query parser.
     *
     * Le traitement actuel est plus simple mais semble fonctionner.
     *
     * @param string $equation l'�quation � tester.
     */
    private function addBrackets(& $equation)
    {
        static $re='~\((?:(?>[^()]+)|(?R))*\)|"[^"]*"|\[[^]]*\]~';

        if (false !== strpos(preg_replace($re, '', $equation), ' '))
            $equation='('.$equation.')';

        /*
        Explications sur l'expression r�guli�re utilis�e.
        On veut �liminer de l'�quation les expressions parenth�s�es, les phrases
        et les articles.

        Une expression parenth�s�e est d�finie par l'expression r�guli�re
        r�cursive suivante (source : manuel php, rechercher "masques r�cursifs"
        dans la page http://docs.php.net/manual/fr/regexp.reference.php) :

        $parent='
            \(                  # une parenth�se ouvrante
            (?:                 # d�but du groupe qui d�finit une expression parenth�s�e
                (?>             # "atomic grouping", supprime le backtracing (plus rapide)
                    [^()]+      # une suite quelconque de caract�res, hormis des parenth�ses
                )
                |
                (?R)            # ou un expression parenth�s�e (appel r�cursif : groupe en cours)
            )*
            \)                  # une parenth�se fermante
        ';

        Une phrase, avec le bloc suivant :
        $phrase='
            "                   # un guillemet ouvrant
            [^"]*               # une suite quelconque de caract�res, sauf des guillemets
            "                   # un guillemet fermant
        ';

        Et une recherche � l'article avec l'expression suivante :
        $value='
            \[                  # un crochet ouvrant
            [^]]*               # une suite quelconque de caract�res, sauf un crochet fermant
            \]                  # un crochet fermant
        ';

        Si on veut les trois, il suffit de les combiner :
        $re="~$parent|$phrase|$value~x";

        Ce qui donne l'expression r�guli�re utilis�e dans le code :
        */
    }
}
?>