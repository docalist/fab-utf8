<?php
function autoload($className)
{$sav=$className;
    $className = ltrim($className, '\\');
    $fileName  = '';
    $namespace = '';
    if ($lastNsPos = strripos($className, '\\')) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }
    $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

    $fileName = __DIR__ . '/../src/' . $fileName;
    $fileName=realpath($fileName);
//     echo "$sav -> $fileName<br />";
    if ($fileName) require $fileName;
}
spl_autoload_register('autoload');

header('content-type: text/plain; charset=utf-8');

// echo "options par défaut : \n";
// new Fab\Store\XapianStore;

require (__DIR__ . '/../lib/Xapian/xapian.php');
require_once(__DIR__ . '/../core/Multimap/Multimap.php');

// new api multicoll
$schema = new Fab\Schema\Schema();
//$schema->stopwords = array_flip(array('le', 'la', 'les', 'de','du','des','a','c', 'en'));
$schema->stopwords = 'le la les de du des a c en';

$catalog = new Fab\Schema\Collection(array('_id'=>'a', 'name'=>'catalog'));

$catalog
->addField(array('_id'=>1, 'name'=>'REF'    , 'analyzer'=>array('Fab\Indexing\Integer', 'Fab\Indexing\Attribute')))
->addField(array('_id'=>2, 'name'=>'Type'   , 'analyzer'=>array('Fab\Indexing\StandardValuesAnalyzer', 'Fab\Indexing\Attribute')))
->addField(array('_id'=>3, 'name'=>'Titre'  , 'analyzer'=>array('Fab\Indexing\StandardTextAnalyzer','Fab\Indexing\RemoveStopwords')))
->addField(array('_id'=>4, 'name'=>'Aut'    , 'analyzer'=>array('Fab\Indexing\StandardValuesAnalyzer', 'Fab\Indexing\Attribute')))
->addField(array('_id'=>5, 'name'=>'ISBN'   , 'analyzer'=>array('Fab\Indexing\Isbn', 'Fab\Indexing\Attribute')))
->addField(array('_id'=>6, 'name'=>'Visible', 'analyzer'=>array('Fab\Indexing\BooleanExtended', 'Fab\Indexing\Attribute')))
;

$schema->addChild($catalog);
var_dump($schema);
echo $schema->toXml(true);
echo $schema->toJson(true);
die();
// new api multicoll








/*


echo "creation du schéma\n";
$schema = new Fab\Schema\Schema();
$schema
    ->set('docid', 'REF')
    ->set('stopwords', array_flip(array('le', 'la', 'les', 'de','du','des','a','c', 'en')))
    ;

$schema
->addField(array('_id'=>1, 'name'=>'REF'    , 'analyzer'=>array('Fab\Indexing\Integer', 'Fab\Indexing\Attribute')))
->addField(array('_id'=>2, 'name'=>'Type'   , 'analyzer'=>array('Fab\Indexing\StandardValuesAnalyzer', 'Fab\Indexing\Attribute')))
->addField(array('_id'=>3, 'name'=>'Titre'  , 'analyzer'=>array('Fab\Indexing\StandardTextAnalyzer','Fab\Indexing\RemoveStopwords')))
->addField(array('_id'=>4, 'name'=>'Aut'    , 'analyzer'=>array('Fab\Indexing\StandardValuesAnalyzer', 'Fab\Indexing\Attribute')))
->addField(array('_id'=>5, 'name'=>'ISBN'   , 'analyzer'=>array('Fab\Indexing\Isbn', 'Fab\Indexing\Attribute')))
->addField(array('_id'=>6, 'name'=>'Visible', 'analyzer'=>array('Fab\Indexing\BooleanExtended', 'Fab\Indexing\Attribute')))
;
*/
/*
$schema->getChild('fields')
->addChild($schema::create('field', array('_id'=>1, 'name'=>'REF'    , 'analyzer'=>array('Fab\Indexing\Integer', 'Fab\Indexing\Attribute'))))
->addChild($schema::create('field', array('_id'=>2, 'name'=>'Type'   , 'analyzer'=>array('Fab\Indexing\StandardValuesAnalyzer', 'Fab\Indexing\Attribute'))))
->addChild($schema::create('field', array('_id'=>3, 'name'=>'Titre'  , 'analyzer'=>array('Fab\Indexing\StandardTextAnalyzer','Fab\Indexing\RemoveStopwords'))))
->addChild($schema::create('field', array('_id'=>4, 'name'=>'Aut'    , 'analyzer'=>array('Fab\Indexing\StandardValuesAnalyzer', 'Fab\Indexing\Attribute'))))
->addChild($schema::create('field', array('_id'=>5, 'name'=>'ISBN'   , 'analyzer'=>array('Fab\Indexing\Isbn', 'Fab\Indexing\Attribute'))))
->addChild($schema::create('field', array('_id'=>6, 'name'=>'Visible', 'analyzer'=>array('Fab\Indexing\BooleanExtended', 'Fab\Indexing\Attribute'))))
;
*/

/*
$stopwords=array('c', 'l', 'il', 'les', 'de', 'avec', 'du', 'des');
$schema->set('stopwords', array_flip($stopwords));
$field = $schema->getChild('fields')->getChild('Titre');
$test = array
(
	"C'est l'été, il fait beau, vivement les vacances.",
	"test de lemmatisation, hopital, hopitaux, hospitalisation",
	"<p><em>Test</em> avec du html conten&#x0041;nt des &quot;entit&eacute;s&quot; html",
);
//$test = array("978-2-1234-5680-3", "1-1234-5680-2", "+++8888aa");
$data = new Fab\Indexing\AnalyzerData($field, $test);

$data->dump('Contenu initial');

//foreach(array('Isbn', 'Countable') as $class)
foreach(array('Lookup','StripTags','Words', 'Phrases', 'Keywords', 'Spellings', 'Attribute', 'RemoveStopwords', 'StemFrench') as $class)
{
    $class='Fab\\Indexing\\' .$class;
    $a = new $class();
    $a->analyze($data);
    $data->dump($class);
}
die();
*/

//echo $schema->toXml(true);
$db = new Fab\Store\XapianStore(array('path'=>'f:/temp/test', 'overwrite'=>true, 'schema'=>$schema));

echo "Ajout d'un enreg\n";
for ($ref=123; $ref<=123; $ref++)
{
$doc = new Fab\Document\Document(array(
    'REF'=>$ref,
    'Type'=>array('Article','Document électronique'),
    'Titre'=>'Premier essai <i>(sous-titre en italique)</i>',
    'Aut'=>'Ménard (D.)',
    'ISBN'=>array("978-2-1234-5680-3", "2-1234-5680-2"),
    'Visible'=>true,
));

//echo $doc->toJson();
$db->put($doc);
}

echo "Appelle get(123)\n";
$doc2 = $db->delete(123);
var_export($doc2->toArray());

echo "Appelle get(123)\n";
$docset = $db->getMany(array(123,123,123));
var_dump($docset);

die();



new Fab\Indexing\Mapper\DefaultMapper();
new Fab\Indexing\Mapper\HtmlMapper();

new Fab\Indexing\Tokenizer\BooleanTokenizer();
//new Fab\Indexing\Tokenizer\DateTokenizer();
new Fab\Indexing\Tokenizer\IntegerTokenizer();
new Fab\Indexing\Tokenizer\PhraseTokenizer();
new Fab\Indexing\Tokenizer\WordTokenizer();

new Fab\Indexing\Filter\ConcatFilter();
new Fab\Indexing\Filter\CountableFilter();
new Fab\Indexing\Filter\IsbnFilter();
new Fab\Indexing\Filter\KeywordFilter();
new Fab\Indexing\Filter\StopwordFilter();
require (__DIR__ . '/../lib/Xapian/xapian.php');
$s = new Fab\Indexing\Filter\XapianFrenchStemmer();
echo "<pre>";
$m = new Fab\Indexing\Mapper\DefaultMapper();
$t = new Fab\Indexing\Tokenizer\WordTokenizer();
$s = new Fab\Indexing\Filter\XapianFrenchStemmer();
$values = array("Le 21 juillet, c'est la fête de l'été. Signature: D.M. Date : 16/11/2011. ");
$m->apply($values);
var_dump($values);
$t->apply($values);
var_dump($values);
$s->apply($values);
var_dump($values);
die();



require_once(__DIR__ . '/../lib/Xapian/xapian.php');
require_once(__DIR__ . '/../core/Multimap/Multimap.php');
require_once(__DIR__ . '/../core/database/Schema.php');
require_once(__DIR__ . '/../core/database/interfaces.php');
