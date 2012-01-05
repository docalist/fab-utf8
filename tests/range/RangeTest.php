<?php

require_once(Runtime::$fabRoot.'core/helpers/Range/Range.php');

define('DEBUG', true);  // mettre � true si on est en phase de d�buggage

//if(DEBUG)
//    set_time_limit(2);      // par s�curit�


class RangeTest extends AutoTestCase
{
    function setUp()
    {
    }
    
    function tearDown()
    {
    }
   
   
    /*
     * En fonction des donn�es de $tests, cr�� un objet Range, boucle dessus et indique le statut
     * succ�s/�chec en fonction du r�sultat attendu
     * 
     * @param $tests tableau contenant les tests au format 'nom test' => array(array($d�but, $fin, [$pas]), array([$attendu1] , [$attendu2] [,...]))
     * 
     */
    function evaluateBehaviour($tests)
    {
        foreach($tests as $title=>$test)
        {
            $input = $test[0];      // donn�es d'entr�e
            $expected = $test[1];    // donn�es attendues en sortie
             
            // Y-a-t-il une variable correspondante au pas en param�tre?
            if (! array_key_exists(2, $input ) )           
                $range = new Range($input[0], $input[1]);
            else
                $range = new Range($input[0], $input[1], $input[2]);
            
            // ajoute l'index d'it�ration en cours au tableau de r�sultat
            foreach($range as $output)
                $result[]=$output;      

            $this->assertNoDiff($expected, $result, $title);
            
            // Pour le prochain test : prochain tour de la boucle principale
            unset($result);

        }
    }
    
    
    
    /*
     * Similaire � evaluateBehaviour sauf qu'on teste si les cas g�n�rent une exception
     * 
     * @param $tests tableau contenant les tests au format 'nom test' => array($d�but, $fin, [$pas])
     */
    function evaluateException($tests)
    {
        
        foreach($tests as $title=>$test)
        {             

            try     // g�n�rera probablement une exception
            {                
                if (! array_key_exists(2, $test) )           // Y-a-t-il une variable correspondante au pas en param�tre?
                    $range = new Range($test[0], $test[1]);
                else
                    $range = new Range($test[0], $test[1], $test[2]);

            }
            catch (Exception $e)    // une exception a �t� g�n�r�e donc succ�s
            {
                continue;  
            }
            
            return $this->fail();
        }
    }
    
    
    
    /*
     * Tests de la classe Range
     */
    function testLoop()
    {    
        // La s�rie de tests qu'on souhaite r�aliser et qui ne doivent pas g�n�rer d'exception (les cas devant en g�n�rer sont test�s apr�s)
        // Chaque test est au format : "nom du test" => array( array(d�but, fin[, pas]), array([r�sultat1,[r�sultat2,...]])

        $testsNoException = array
        (
            'D�but < Fin avec un pas automatique' => array
            (
                array(2.3, 8),
                array(2.3, 3.3, 4.3, 5.3, 6.3, 7.3)
            ),
            
            'D�but > Fin avec un pas automatique' => array
            (
                array(5, 1),
                array(5, 4, 3, 2, 1)
            ),
            
            'D�but = Fin avec un pas automatique' => array
            (
                array(4.2, 4.2),
                array(4.2)
            ),
            
            'D�but < Fin avec un pas positif' => array
            (
                array(2.1, 7.1, 1.1),
                array(2.1, 3.2, 4.3, 5.4, 6.5)
            ),
            
            'D�but > Fin avec un pas n�gatif' => array
            (
                array(5.6, 0.74, -2),
                array(5.60000000000000001, 3.6, 1.6)
            ),
            
            'D�but = Fin avec un pas n�gatif' => array
            (
                array(0, 0, -1),
                array(0)
            ),
            
            // M�me chose avec des caract�res maintenant
            
            'Caract�res - D�but < Fin avec un pas automatique' => array
            (
                array('a', 'd'),
                array('a', 'b', 'c', 'd')
            ),
            
            'Caract�res - D�but > Fin avec un pas automatique' => array
            (
                array('g', 'c'),
                array('g', 'f', 'e', 'd', 'c')
            ),
            
            'Caract�res - D�but = Fin avec un pas automatique' => array
            (
                array('v', 'v'),
                array('v')
            ),
            
            'Caract�res - D�but < Fin avec un pas positif' => array
            (
                array('a', 'k', 3),
                array('a', 'd', 'g', 'j'), 
            ),
            
            'Caract�res - D�but > Fin avec un pas n�gatif' => array
            (
                array('G', 'C', -2),
                array('G', 'E', 'C')
            ),
            
            'Caract�res - D�but = Fin avec un pas n�gatif' => array
            (
                array('s', 's', -6),
                array('s')
            )
        );
        
        //effectue les tests
        $this->evaluateBehaviour($testsNoException);
        
        
        // la s�rie de tests qui doivent g�n�rer une exception
        $exceptionExpected = array
        (
            'D�but < Fin avec un pas n�gatif' => array(5.2, 9, -3),
            
            'D�but > Fin avec un pas positif' => array(12.36, -2, 5.14),
            
            'Un entier et un caract�re' => array(10, 'g'),
            
            'Un caract�re et un r�el' => array('j', -2.31),
            
            'D�but et fin de type caract�re avec un pas de type float' => array('h', 'l', 2.4),
            
            'Un des arguments est une cha�ne de caract�re' => array('A string', 'f'),
            
            'D�but est une minuscule et Fin est une majuscule' => array('f', 'A'),
            
            'D�but est une majuscule et Fin est une minuscule' => array('s', 'Y')
        );
        
        $this->evaluateException($exceptionExpected);
    }
}   // end class

?>