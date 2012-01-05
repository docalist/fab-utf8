<?php
class FormBuilder extends Module
{
    public function actionIndex()
    {
        return Response::create('html')
        ->setHeader('Content-Type', 'application/xhtml+xml;charset=ISO-8859-1')  // si le document est servi en xml, on obtient en r�sultat de l'�diteur un source xml correct (<br />, hr/>..) si on le sert en html standard, ce n'est pas le cas (<hr><br>)
                                                                                        // cf aussi http://www.stevetucker.co.uk/page-innerxhtml.php
        ->setTemplate
        (
            $this,
            'FormBuilder.html',
            array
            (
                'template' => strtr(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'empty.html', '\\', '/'),
            )
        );
    }

    public function actionSave($template, $source)
    {
        file_put_contents($template, $source);
        echo "template $template enregistr� :<br />";
        echo "<pre>", htmlspecialchars($source), "</pre>";
        echo '<a href="', Routing::linkFor('Index'), '">retour index</a>';
    }

    /**
     * Charge un template et transforme son code source pour qu'il puisse �tre charg� dans
     * l'�diteur.
     *
     * La compilation consiste � instancier le template, mais sans le compiler. L'instanciation
     * se fait en deux passes.
     * - tout d'abord, on instancie le code avec le template match "text.html" pour transformer
     * en blocs �ditables tous les noeuds de type "texte" qui figurent dans le template.
     * - ensuite, on instancie tous les tags avec les templates pr�sents dans le fichier
     * incude "templates.html" pour les trasnformer en blocs �ditables.
     *
     * On fait l'instanciation en deux passes car sinon on instancierait �galement les neouds de
     * type texte pr�sents dans les tempaltes match.
     *
     * @param path $template
     */
    public function actionLoad($template)
    {
        // Recherche le path absolu du template indiqu�
        if (Utils::isRelativePath($template))
        {
            $sav = $template;
            if (false === $template=Utils::searchFile($template))
                throw new Exception("Impossible de trouver le template $sav. searchPath=".print_r(Utils::$searchPath, true));
        }

        // Charge le code source du template
        $source = file_get_contents($template);

        // Echappe le code pr�sent dans le template pour �viter toute erreur
        $source = strtr($source, array('$' => '\$','{' => '\{','}' => '\}',));

        $sav = Config::get('templates.autoinclude');

        Config::set('templates.autoinclude', array('include/text.html'));
        $source = TemplateCompiler::instantiate($source);

        Config::set('templates.autoinclude', array('include/templates.html'));
        $source = TemplateCompiler::instantiate($source);

        Config::set('templates.autoinclude', $sav);

        // Ex�cute le template
        Template::runSource(__FILE__, $source);

        // Remarque : en plus des autoincludes standard, l'action Load a l'autoinclude
        // "templates.html" qui se charge de matcher tous les contr�les qu'on connait et
        // de les encadrer avec un <div class="fbitem" />
    }

    /**
     * G�n�re le code source d'un widget.
     *
     * L'action est appell�e avec en param�tre :
     * - le nom du widget � g�n�rer
     * - les parm�tres de ce widget
     *
     * @param $widget
     */
    public function actionRender($widget, $content='')
    {
        $source = '<' . $widget;
        foreach($this->request->clear('widget')->clear('content')->getParameters() as $name=>$value)
        {
            $value = strtr($value, array('$' => '\$','{' => '\{','}' => '\}',));
            $source .= " $name=\"$value\"";
        }
        $source .= ">$content</$widget>";

        $sav = Config::get('templates.autoinclude');
        Config::set('templates.autoinclude', array('include/text.html'));
        $source = TemplateCompiler::instantiate($source);
        Config::set('templates.autoinclude', $sav);
//Config::set('templates.autoinclude', array('include/templates.html'));
//var_export(Config::set('templates.autoinclude.formbuilder', false));
//        echo TemplateCompiler::instantiate($source); // � 9h00
//        die();
//die($source);
        Template::runSource(__FILE__, $source);

//        return Response::create('text')->setContent($source);
    }

    public function readFile($path)
    {
        return file_get_contents(dirname(__FILE__).DIRECTORY_SEPARATOR . $path);
    }

    public function getTools($json=true)
    {
        // Groupes d'outils disponibles
        $toolGroups = Config::get('tools-groups');
//        echo "Liste compl�te des groupes : <pre>", var_export($toolGroups,true), "</pre>";

        // Liste des outils � afficher dans la barre d'outils
        $tools = Config::get('tools');
//        echo "Outils � afficher : <pre>", var_export($tools,true), "</pre>";

        // Ne conserve dans les groupes que les outils � afficher
        foreach($toolGroups as $groupName => & $group)
        {
            // Si c'est un nom de groupe, on garde tous les outils d�finis dans ce groupe
            if (! array_key_exists($groupName, $tools))
            {
//                echo "Le groupe ", var_export($groupName,true)," n'est pas disponible en entier : isset(tools[$groupName])==false<br /><blockquote />";
                foreach($group['tools'] as $toolName => $tool)
                {
                    if (! array_key_exists($toolName, $tools))
                    {
                        unset($group['tools'][$toolName]);
//                        echo "supprime l'outil $toolName du groupe $groupName<br />";
                    }
                }

                if (empty($group['tools']))
                {
                    unset($toolGroups[$groupName]);
//                    echo "supprime le groupe $groupName d�sormais vide<br />";
                    continue;
                }
//                echo "</blockquote>";
            }
//            else echo "conserve le groupe $groupName<br />";

            // Construit la liste des attributs de chaque outil

            foreach($group['tools'] as $toolName => & $tool)
            {
                $tool['widget'] = $toolName;
//                echo "Construction des attributs de $toolName<br /><blockquote>";
                $result = array();
                if (isset($tool['attributes']))
                {
                    foreach($tool['attributes'] as $name=>$attribute)
                    {
                        if (! $this->getAttributesGroup($name, $result))
                        {
                            $result[$toolName][$name] = $attribute;
                        }
                    }
                }
//                else
//                    echo "Aucun attribut d�finit<br />";
                $tool['attributes'] = $result;
//                echo "</blockquote>";
            }
            unset($tool); // ref foreach
        }
        unset($group); // ref foreach

//        echo "R�sultat : <pre>", var_export($toolGroups,true), "</pre>";
/*
        foreach($toolGroups as $groupName=>$group)
        {
            echo (isset($group['label']) ? $group['label'] : $groupName), '<ul>';
            foreach($group['tools'] as $toolName => $tools)
                echo "<li>", $toolName, "</li>";
            echo "</ul>";
        }

        $json = json_encode($toolGroups);
        echo strlen($json), '<br />';
        echo $json;
*/
        return $json ? json_encode(Utils::Utf8Encode($toolGroups)) : $toolGroups;
    }

    private function getAttributesGroup($group, & $result)
    {
        $groups = Config::get("attributes-groups", array());
        if (! isset($groups[$group])) return false;
//        echo '<blockquote>';
        foreach($groups[$group] as $name => $attribute)
        {
            if (! $this->getAttributesGroup($name, $result))
            {
//                echo "Attribut $name du sous-groupe $group ajout� au groupe d'attributs $group<br />";
                $result[$group][$name] = $attribute;
            }
        }
//        echo '</blockquote>';
        return true;
    }
}