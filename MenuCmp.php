<?php
namespace App\Components\MenuCmp;

use \App\MyFunctions\Func;

class MenuCmp extends \App\Components\BaseStandardCmp\BaseStandardCmp
{    
    /** @var MenuCmpFactory factory */
    /** @var int id */
    
    public $menuItemId = null;
    
    //TODO: do BaseStandardCmp dát, $model = $this->factory->modelTexty; $row = $model->findOneById(...); tady odstranit kod který se duplikuje (row=findObyById)
    public function renderDefault()
    {
        $this->setTemplate();
	
	if(!$row = $this->factory->modelMenu->findOneById($this->id)){ //když nebyl záznam nalezen, založ nový
//	    $row = $this->factory->modelTexty->insert(Func::arrHash(array("id"=>$this->id, "text"=>"New text.")));
	    $this->flashMessage("Komponentě nebyl přiřazen žádný záznam, proto byl založen nový :)");
	    $polozky = null;
	}
	else{
	    $polozky = $this->factory->modelPolozkyMenu->findBy(array("cmp_menu_id"=>$row->id, "viditelnost"=>TRUE))->order("poradi");
	}
	
	$this->template->row = $row;
	
	$this->template->polozkyMenu = $polozky;
    }
    
    public function renderEdit()
    {
        $this->setTemplate();
	if(!$row = $this->factory->modelMenu->findOneById($this->id)){ //když nebyl záznam nalezen, založ nový
	    $this->flashMessage("Komponentě nebyl přiřazen žádný záznam :)");
	}
	else{
	    $polozky = $this->factory->modelPolozkyMenu->findBy(array("cmp_menu_id"=>$row->id, "viditelnost"=>TRUE))->order("poradi");
	}
	
	$this->template->row = $row;
	
	$this->template->polozkyMenu = $polozky;
	
//	$this->template->cmpId = $this->getUniqueId();
    }
    
    protected function createComponentEditForm()
    {
        /** @var \Nette\Application\UI\Form */
        $form = $this->factory->textFormFactory->create(); //TODO: dodat do ImgFactory parametr ... (asi presenter), abych mohl ověřit práva uživatele při validaci ve formu..., ještě zkusit začít transakci ve formu (když se obrázek ukládá a pak jí zde nakonci, když všechno projde jak má, commitnou, jinar rollback)
        $form->onSuccess[] = function ($form) {
            if($this->presenter->user->isAllowed("sprava-obsahu"))
            {
                $this->flashMessage("uloženo");
                if($this->presenter->isAjax()){
                    $this->mode = self::modeDefault;
		    $this->redrawControl();
		    
		    if(isset($this->presenter["quickAdminMenu"])){
			$this->presenter["quickAdminMenu"]->redrawControl();
		    }
                }
                else{
                    $this->presenter->redirectUrl($this->link("this")."#".$this->uniqueId);
                }
            }
            else{
               $form->addError("nemáte oprávnění"); 
            }
        };

        return $form;
    }
    
    //insert new item
    public function renderQuickAdmin()
    {
	//insert new item (nezávisle na tom, jestli předám komponentě id nebo ne .... v quickAdminu je komponenta vypisována za účelem přidání záznamu. Když jde o editaci, je předána instance již existující komponenty a tato metoda se nerenderuje
        $this->setTemplate();
	if($row = $this->factory->modelPolozkyMenu->findOneById($this->menuItemId)){
//	    dump($row);
	    $this->template->menuItem = $row;
	    $this["editMenuItem"]["itemId"]->setValue($row->id);
	    $this["editMenuItem"]["nazev"]->setValue($row->nazev);
	    $this["editMenuItem"]["page"]->setValue($row->cmpbase_stranky->id);
	    $this["editMenuItem"]["editedMenuCmpName"]->setValue($this->presenter["quickAdminMenu"]->template->quickAdminCurrEditCmp->getUniqueId());
	    $this->template->pages = $this->factory->modelStranky->findAll();
	}
	if($this->menuItemId == "new"){
	    $this->template->menuItem = "new";
	    $this->template->pages = $this->factory->modelStranky->findAll();
	}
        
	$this->template->cmpId = $this->getUniqueId();
    }
    
    public function renderAdmin()
    {
        $this->setTemplate();
	if($this->id){
	    //update item
	}
	
	if($textRow = $this->factory->modelTexty->findOneById($this->id)){
	    $this->template->textRow = $textRow;
	}
	else {
	    $this->template->textRow = \Nette\ArrayHash::from(array("id"=>"", "text"=>"komponentě není v db přiřazen žádný text"));
	}
    }
    
    public function handleUpdateMenuItemsPosition()
    {
	$postParams = $this->presenter->context->getByType('Nette\Http\Request')->getPost();
	$menuItems = explode("&", str_replace("menuItems[]=", "", $postParams["menuPos"]));
        if($this->presenter->user->isAllowed("sprava-obsahu")){
	    $queryStr = "UPDATE cmp_polozkymenu SET poradi = case id ";
	    foreach($menuItems as $itemId){
		$poradi = array_search($itemId, $menuItems);
		$queryStr .= "when $itemId then $poradi ";
	    }
	    $arrValues = implode(",", $menuItems);
	    $queryStr .= "end WHERE id in ($arrValues)";
	    
	    $dbCon = $this->presenter->context->getService("database.default");
	    $result = $dbCon->query($queryStr);
	    
	    if(isset($postParams["cmpMode"])){
		if($postParams["cmpMode"]=="edit")
		    $this->mode = self::modeEdit;
	    }
	    
	    if ($this->presenter->isAjax()){
		$this->redrawControl();
	    }
	}
    }
    
    public function handleEditMenuItem($itemId){
	$this->menuItemId = $itemId;
	
	parent::handleEdit();
	
	
	//nastavím id menu itemu - v nsledném renderu spravuji položku menu
//	$this->id = $itemId;
    }
    
    public function createComponentEditMenuItem(){
	$form = new \Nette\Application\UI\Form;
	$form->addHidden("itemId");
	$form->addHidden("editedMenuCmpName");
	$form->addText("nazev", "Popisek");
	$pages = $this->factory->modelStranky->findAll()->fetchPairs("id", "title");
	$form->addSelect("page", "Stránka", $pages)->setHtmlId("pageSelect");
	$form->addSubmit("submit", "Uložit");
	
	$form->onSuccess[] = array($this, "editMenuItemSubmited");
	
	return $form;
    }
    public function editMenuItemSubmited($form){
	if($this->presenter->user->isAllowed("sprava-obsahu"))
	{
	    $values = $form->getValues();
	    $editedMenuCmpName=$values->editedMenuCmpName;
	    if($values->itemId){
		$this->factory->modelPolozkyMenu->findOneById($values->itemId)->update(["nazev"=>$values->nazev, "cmpbase_stranky_id"=>$values->page]);
	    }
	    else{
		$values->cmpbase_stranky_id = $values->page;
		$values->cmp_menu_id = $this->id;
		unset($values->page); unset($values->editedMenuCmpName); unset($values->itemId); //úprava pro insert, aby položky v poli seděli s názvy sloupců v db
		$this->factory->modelPolozkyMenu->insert($values);
	    }
	    $this->presenter["quickAdminMenu"]->flashMessage("uloženo");
	    if($this->presenter->isAjax()){
		$this->presenter[$editedMenuCmpName]->redrawControl();
		if(isset($this->presenter["quickAdminMenu"])){
		    $this->presenter["quickAdminMenu"]->redrawControl();
		}
	    }
	    else{
		$this->presenter->redirectUrl($this->link("this")."#".$this->uniqueId);
	    }
	}
	else{
	   $form->addError("nemáte oprávnění"); 
	}
    }
    
    public function handleDeleteItem($id){
	if($this->presenter->user->isAllowed("sprava-obsahu"))
	{
	    $this->factory->modelPolozkyMenu->findOneById($id)->delete();
	    $this->presenter["quickAdminMenu"]->flashMessage("odstraněno");
	    if($this->presenter->isAjax()){
		$this->presenter[$this->presenter->cmpsCache->load("lastEditedCmpName")]->redrawControl(); //refresh menu - naposledy editované komponenty
		if(isset($this->presenter["quickAdminMenu"])){
		    $this->presenter["quickAdminMenu"]->redrawControl();
		}
	    }
	    else{
		$this->presenter->redirectUrl($this->link("this")."#".$this->uniqueId);
	    }
	}
	else{
	   $form->addError("nemáte oprávnění"); 
	}
    }
    
    public function handleAddItem(){
	$this["editMenuItem"]["editedMenuCmpName"]->setValue($this->presenter->cmpsCache->load("lastEditedCmpName"));
		
	//nastav do qaMenu komponentu, která byla nakliklá k editaci
	if(isset($this->presenter["quickAdminMenu"]))
	    $this->presenter["quickAdminMenu"]->template->quickAdminCurrEditCmp = $this;
	
	$this->template->pages = $this->factory->modelStranky->findAll();
	
	$this->template->setFile(__DIR__."/templates/addNewMenuItem.latte");
	
	if($this->presenter->isAjax()){
	    $this->presenter["quickAdminMenu"]->redrawControl();
	}
    }
}

class MenuCmpFactory extends \App\Components\BaseCmp\BaseCmpFactory{    
    /** @inject @var Model\Menu */
    public $modelMenu;
    /** @inject @var Model\PolozkyMenu */
    public $modelPolozkyMenu;
    /** @inject @var \App\Components\BaseStandardCmp\Model\Stranky */
    public $modelStranky;
    
    const inQuickAddMenu = false;
    const title = "Menu";
}
