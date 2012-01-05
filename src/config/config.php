<?php
/*

Fichier de configuration du site web, de l'application
Ce fichier contient uniquement les options de configuration qui ne peuvent
pas �tre stock�es dans les fichiers de configuration.
Par exemple l'option 'cache actif : oui/non' ne peut pas �tre stock�e
dans le fichier de config puisque celui-ci sera compil� avant d'�tre utilis� et que
pendant la compilation, on va essayer de mettre le r�sultat dans le cache alors
que celui-ci n'a pas encore �t� initialis�.

*/

Config::addArray
(
    array
    (
        // Param�tres du cache
        'cache'=>array
        (
            // Indique si on autorise ou non le cache (true/false)
            'enabled'   => true,

            // Path du r�pertoire dans lequel seront stock�s les fichiers
            // de l'application mis en cache.
            // Il peut s'agir d'un chemin absolu (c:/temp/cache/) ou
            // d'un chemin relatif � la racine de l'application ($root)
            // Si cette cl� est vide ou absente, fab essaiera de stocker
            // les fichiers dans le r�pertoire temporaire du syst�me
            // en examinant les variables d'environnement 'TMPDIR','TMP' et
            // 'TEMP'.

            //'path'      => 'c:\\temp',
        )
    )
);
?>