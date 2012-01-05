<?php
/**
 * @package     fab
 * @subpackage  module
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 */

/**
 * Module de gestion d'un panier.
 * 
 * @package     fab
 * @subpackage  modules
 */
class CartModule extends Module
{
	// TODO : Besoin de quantit� ou non � mettre dans config
	// TODO : Panier � cat�gorie ou non : � mettre dans config
	
	public $cart=array();
    
    /**
     * @var boolean Indique si le panier accepte ou non les cat�gories
     */
    public $hasCategory=null;
    
    /**
     * @var boolean Indique si le panier a besoin ou non des quantit�s
     */
    public $hasQuantity=null;
    
    /**
     * Cr�e, ou charge s'il existe d�j�, le panier indiqu� dans la configuration.
     * Si aucun nom, le panier s'appelle cart.
     * Le panier sera automatiquement enregistr� � la fin de la requ�te en cours.
     */
	public function preExecute()
    {
        //parent::preExecute();
        // R�cup�re le nom du panier
        $name=Config::get('name');
        
        // Fab ouvre la session juste avant d'appeller l'action et donc � ce
        // stade (preExecute), la session n'a pas encore �t� charg�e. Comme
        // On utilise des alias pour g�rer le panier, il faut absolument qu'elle
        // soit charg�e, donc on le fait maintenant. 
        Runtime::startSession();
        
        // Cr�e le panier s'il n'existe pas d�j� dans la session
        if (!isset($_SESSION[$name])) 
        {
            $_SESSION[$name]=array();
            $_SESSION[$name.'hascategory']=null;
            $_SESSION[$name.'hasquantity']=Config::get('quantity',false);
        }
        
        // Cr�e une r�f�rence entre notre tableau cart et le tableau stock� 
        // en session pour que le tableau de la session soit automatiquement modifi�
        // et enregistr� 
        $this->cart =& $_SESSION[$name];        
        $this->hasCategory=& $_SESSION[$name.'hascategory'];
        $this->hasQuantity=& $_SESSION[$name.'hasquantity'];

//        if (Utils::isAjax())
//        {
//            $this->setLayout('none');
//            Config::set('debug', false);
//            Config::set('showdebug', false);
//            header('Content-Type: text/html; charset=ISO-8859-1'); // TODO : avoir une rubrique php dans general.config permettant de "forcer" les options de php.ini
//        }
         
	}

    /**
     * Ajoute un ou plusieurs �l�ments dans le panier, en pr�cisant la quantit�.
     * Si une cat�gorie est pr�cis�e, l'�l�ment sera ajout� � cette cat�gorie.
     * 
     * L'action affiche ensuite le template indiqu� dans la cl� <code><template></code>
     * du fichier de configuration, en utilisant le callback indiqu� dans la cl�
     * <code><callback></code> du fichier de configuration.
     * 
     * Pour l'ajout de plusieurs �l�ments :
     * - On suppose que les navigateurs respectent l'ordre dans lequel les param�tres 
     * sont pass�s. On obtient ainsi 3 tableaux (item, category, quantity).
     * item[X] est � ajouter � la cat�gorie category[X], en quantit� quantity[X].
     * - Si une seule cat�gorie et/ou une seule quantit�, alors la cat�gorie et/ou la 
     * quantit� s'appliquent � chaque �l�ment � ajouter.
     *
     * @param array $item l'(les) �l�ment(s) � ajouter
     * @param int|array $quantity la quantit� de chaque �l�ment � ajouter
     * @param string|array $category la cat�gorie de chaque �l�ment � ajouter
     */
	public function actionAdd(array $item, $quantity=1, $category=null)
    {
        /*
         * Notes DM 13/12/07
         * 
         * Avec le nouveau ModuleLoader, les arguments de la requ�te sont
         * pass�s directement en param�tre de l'action.
         * 
         * Du coup, on n'a plus besoin de les r�cup�rer manuellement 
         * (le code Utils::get($_REQUEST['xxx']) qui existait avant).
         * 
         * Par ailleurs, le type des param�tres peut �tre forc�. Par exemple,
         * ici, item a �t� d�clar� comme �tant un tableau. Le ModuleLoader se
         * charge de veiller � ce que les param�tres sient du bon type et en 
         * l'occurence il nous passera toujours un tableau, m�me si l'utilisateur
         * n'a indiqu� qu'un seul item.
         * 
         * Cela simplifie le code parce que du coup on n'a plus � tester les deux
         * cas (un item seul / un tableau d'items) : on a toujours un tableau,
         * contenant �ventuellement un seul �l�ment.
         * 
         * Le nouvel objet Request permet �galement de v�rifier facilement le
         * type des aguments. Par exemple, avant, on ne v�rifiait pas que le(s)
         * quantity(s) pass�(s) en param�tre �tai(en)t un(des) entier(s).
         * Que se passait-il si on appellait add?item=&quantity=abcd ?
         * Maintenant, item est obligatoire (parce qu'il est d�clar� sans valeur
         * par d�faut dans les param�tres de l'action) et quantity est test� 
         * (0 < entier < 100)
         *  
         * Pour le moment, j'ai uniquement mis le code obsol�te en commentaires, 
         * le temps de valider tout �a. 
         * 
         * SF : 
         * - faire le m�nage une fois qu'on sera sur que tout fonctionne bien.
         * - faire la m�me chose pour les autres actions de CartModule
         * 
         * Remarque : les templates teste explicitement 'if(is_array(item))'. 
         * Ils doivent �tre adapt�s pour tester � la place 'if(count(item)>1)'
         */
	    
        // Fait des v�rifications sur la quantit�
        $this->request->int('quantity')->min(1)->max(100)->ok();
        
		// Plusieurs �l�ments � ajouter
		$nb=0;
//		if (is_array($item))
//		{
			if (isset($category) && is_array($category) && (count($category)!= count($item)))
				throw new Exception('Erreur : il doit y avoir autant de cat�gories que d\'�l�ments � ajouter.');
			
			if (isset($quantity) && is_array($quantity) && (count($quantity)!= count($item)))
				throw new Exception('Une quantit� doit �tre pr�cis�e pour chaque �l�ment � ajouter.');
		
			foreach($item as $key=>$value)
			{
				// Si on a une cat�gorie pour chaque �l�ment
				(is_array($category)) ? $cat=$category[$key] : $cat=$category;
				
				// Si une quantit� pour chaque �l�ment
				(is_array($quantity)) ? $quant=$quantity[$key] : $quant=$quantity;
				
				// Ajoute l'�l�ment au panier
				$this->add($value, $quant, $cat);
				++$nb;
			}
//		}
		
		// Un seul �l�ment � ajouter
//		else
//		{
//			// Ajoute l'item au panier
//			$this->add($item, $quantity, $category);
//			$nb=1;
//		}
		
		if (Utils::isAjax())
        {
        	if ($nb===1)
                echo $nb . ' notice ajout�e au panier';
            else
                echo $nb . ' notices ajout�es au panier';
            
            return;
        }
        
		// D�termine le callback � utiliser
		// TODO : V�rifier que le callback existe
		$callback=Config::get('callback');
		
		// Ex�cute le template, s'il a �t� indiqu�
		if ($template=Config::get('template'))
			Template::run
			(
				$template,
				array($this, $callback),
                array('category'=>$category, 'item'=>$item, 'quantity'=>$quantity)
			);
	}
	
	/**
	 * Supprime un ou plusieurs �l�ments du panier, en pr�cisant la quantit�.
	 * Si une cat�gorie a �t� pr�cis�e, supprime l'�l�ment, de cette cat�gorie.
	 * 
     * L'action affiche ensuite le template indiqu� dans la cl� <code><template></code>
     * du fichier de configuration, en utilisant le callback indiqu� dans la cl�
     * <code><callback></code> du fichier de configuration.
     * 
	 * Pour la suppression de plusieurs �l�ments :
	 * - On suppose que les navigateurs respectent l'ordre dans lequel les param�tres 
	 * sont pass�s. On obtient ainsi 3 tableaux (item, category, quantity).
	 * item[X] est � supprimer de la cat�gorie category[X], en quantit� quantity[X].
	 * - Si une seule cat�gorie et/ou une seule quantit�, alors la cat�gorie et/ou la 
	 * quantit� s'appliquent � chaque �l�ment � supprimer.
     *
     * @param array $item l'(les) �l�ment(s) � supprimer
     * @param int|array $quantity la quantit� de chaque �l�ment � supprimer
     * @param string|array $category la cat�gorie de chaque �l�ment � supprimer
	 */
	public function actionRemove(array $item, $quantity=1, $category=null)
	{
	    // Fait des v�rifications sur la quantit�
	    $this->request->int('quantity')->min(1)->max(100)->ok();
	    
		// Plusieurs �l�ments � supprimer
//		if (is_array($item))
//		{
			if (isset($category) && is_array($category) && (count($category)!= count($item)))
				throw new Exception('Erreur : il doit y avoir autant de cat�gories que d\'�l�ments � supprimer.');
			
			if (isset($quantity) && is_array($quantity) && (count($quantity)!= count($item)))
				throw new Exception('Une quantit� doit �tre pr�cis�e pour chaque �l�ment � supprimer.');
		
			foreach($item as $key=>$value)
			{
				// Si on a une cat�gorie pour chaque �l�ment
				(is_array($category)) ? $cat=$category[$key] : $cat=$category;
				
				// Si une quantit� pour chaque �l�ment
				(is_array($quantity)) ? $quant=$quantity[$key] : $quant=$quantity;
				
				// Supprime l'�l�ment du panier
				$this->remove($value, $quant, $cat);
			}
//		}
//		
//		// Un seul �l�ment � supprimer
//		else
//		{
//			$this->remove($item, $quantity, $category);
//		}

		// D�termine le callback � utiliser
		// TODO : V�rifier que le callback existe
		$callback=Config::get('callback');
		
		// Ex�cute le template, s'il a �t� indiqu�
		if ($template=Config::get('template'))
			Template::run
			(
				$template,
				array($this, $callback),
                array('category'=>$category, 'item'=>$item, 'quantity'=>$quantity)
			);
	}
	
	/**
	 * Vide la totalit� du panier ou supprime une cat�gorie d'�l�ments du panier.
	 * 
     * Apr�s la suppression des �l�ments du panier, l'action affiche le template 
     * indiqu� dans la cl� <code><template></code> du fichier de configuration, 
     * en utilisant le callback indiqu� dans la cl� <code><callback></code> 
     * du fichier de configuration.
     * 
	 * @param string|null $category la cat�gorie d'�l�ments � supprimer ou null
	 * pour vider la totalit� du panier.
	 */
	public function actionClear($category=null)
	{
		// Vide la cat�gorie ou vide le panier si pas de cat�gorie
		$this->clear($category);
        
		// D�termine le callback � utiliser
		// TODO : V�rifier que le callback existe
		$callback=Config::get('callback');

		// Ex�cute le template, s'il a �t� indiqu�
		if ($template=Config::get('template'))
			Template::run
			(
				$template,
				array($this, $callback),
                array('category'=>$category)
			);
	}
	
	/**
	 * Affiche le panier.
	 *
	 * Affiche le panier en utilisant le template indiqu� dans la cl� 
	 * <code><template></code> du fichier de configuration et le callback
     * indiqu� dans la cl� <code><callback></code> du fichier de configuration.
	 * 
	 * @param string|null $category la cat�gorie des �l�ments � afficher ou null
	 * pour afficher tout le panier.
	 */
	public function actionShow($category=null)
	{
	    // V�rifie que la cat�gorie existe
        if ($category)
        {
        	if ($this->hasCategory)
        	{
	        	if (! isset($this->cart[$category]))
	        		throw new Exception('La cat�gorie demand�e n\'existe pas.');
        	}
        }
        
		// D�termine le template � utiliser
		if (! $template=Config::get('template'))
			throw new Exception('Le template � utiliser n\'a pas �t� indiqu�');
			
		// D�termine le callback � utiliser
		$callback=Config::get('callback');
		
		// Ex�cute le template
		Template::run
		(
			$template,
			array($this, $callback),
            array('category'=>$category)
		);
	}
	
    
    /**
     * Ajoute un �l�ment dans le panier.
     * 
     * @param mixed $item l'�l�ment � ajouter
     * @param int $quantity la quantit� d'�l�ment $item
     * @param mixed $category optionnel la cat�gorie dans laquelle on veut ajouter
     * l'item
     */
    private function add($item, $quantity=1, $category=null)
    {
        if ($quantity<0) return $this->remove($item, $quantity, $category);
        
        // Le 1er ajout d'un item d�finit si le panier a des cat�gories ou pas
        if (is_null($this->hasCategory))
            $this->hasCategory=(!is_null($category));
        else
        {
            if ($this->hasCategory)
            {
                if (is_null($category)) throw new Exception('Vous devez sp�cifier une cat�gorie');
            }
            else
            {
                if (!is_null($category)) throw new Exception('Cat�gorie non autoris�e');
            }
        }
        
		if (is_int($item) || ctype_digit($item)) $item=(int)$item;     	

		// Ajoute l'item dans le panier
		if (is_null($category))
        {
            if ($this->hasQuantity)
            {
                if (isset($this->cart[$item])) 
                    $this->cart[$item]+=$quantity; 
                else      
                    $this->cart[$item]=$quantity;
            }
            else
            {
                // On met la quantit� � 1 quand le panier n'a pas besoin de quantit�
                $this->cart[$item]=1;
            }
        }
        else
        {        
            if ($this->hasQuantity)
            {
                if (! isset($this->cart[$category]) || ! isset($this->cart[$category][$item]))
                    $this->cart[$category][$item]=$quantity;
                else
                    $this->cart[$category][$item]+=$quantity;
            }
            else
            {
                // On met la quantit� � 1 quand le panier n'a pas besoin de quantit�
                $this->cart[$category][$item]=1;
            }
        }
    }
    
     
    /**
     * Supprime un �l�ment du panier 
     * Si une cat�gorie est pr�cis�e en param�tre, supprime l'item, de la cat�gorie.
     * Supprime la cat�gorie, si elle ne contient plus d'�l�ment.
     * remarque : Aucune erreur n'est g�n�r�e si l'item ne figure pas dans le
     * panier
	 *
	 * @param mixed $item l'item � supprimer
     * @param int $quantity la quantit� d'item � supprimer
     * @param mixed $category optionnel la cat�gorie dans laquelle on veut supprimer
     * l'item
     */
    private function remove($item, $quantity=1, $category=null)
    {
        if (is_int($item) || ctype_digit($item)) $item=(int)$item;

        if (! is_null($this->hasCategory))
        {
            if ($this->hasCategory)
            {
                if (is_null($category)) throw new Exception('Vous devez sp�cifier une cat�gorie');
            }
            else
            {
                if (!is_null($category)) throw new Exception('Cat�gorie non autoris�e');
            }
        }
                
        // Supprime l'�l�ment du panier
        if (is_null($category))
        {
        	if (! isset($this->cart[$item])) return;
            $this->cart[$item]-=$quantity;
            if ($this->cart[$item]<=0) unset($this->cart[$item]);
        }
        else
        {
            if (! isset($this->cart[$category])) return;
            if (! isset($this->cart[$category][$item])) return;

            $this->cart[$category][$item]-=$quantity;
            if ($this->cart[$category][$item]<=0) unset($this->cart[$category][$item]);
            
	        // Si plus d'�l�ment dans la cat�gorie, supprime la cat�gorie
			if (count($this->cart[$category]) == 0)
				unset($this->cart[$category]);        	
        }

        // Si le panier est vide, r�initialise le flag hasCategory
        if (count($this->cart) == 0) $this->hasCategory=null;
    }
    

    /**
     * Vide la totalit� du panier ou supprime une cat�gorie d'items du panier 
     * 
     * @param string|null $category la cat�gorie � supprimer
     */
    private function clear($category=null)
    {
        if (! $this->hasCategory)
        {
            if (!is_null($category)) throw new Exception('Cat�gorie non autoris�e');
        }

        // Si on n'a aucune cat�gorie, vide tout le panier
        if (is_null($category))
        	$this->cart=array();
            
        // Sinon, vide uniquement la cat�gorie indiqu�e
        else
        	unset($this->cart[$category]);

        // Si le panier est compl�tement vide, r�initialise le flag hasCategory
        if (count($this->cart) == 0) $this->hasCategory=null;
    }
	
    public function contains($item)
    {
        return isset($this->cart[$item]);
    }
} 

?>
