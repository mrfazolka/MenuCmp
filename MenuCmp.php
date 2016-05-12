<?php
namespace App\Components\MenuCmp;

use \App\MyFunctions\Func;

class MenuCmp extends \App\Components\BaseStandardCmp\BaseStandardCmp
{    
    /** @var MenuCmpFactory factory */
    /** @var int id */
    
    
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
	    $polozky = $this->factory->modelPolozkyMenu->findBy(array("menu_id"=>$row->id, "viditelnost"=>TRUE))->order("poradi");
	}
	
	$this->template->row = $row;
	
	$this->template->polozkyMenu = $polozky;
    }
    
    public function renderEdit()
    {
        $this->setTemplate();
	if($row = $this->factory->modelTexty->findOneById($this->id)){
            $this["editForm"]["cmp_text_id"]->setValue($row->id);
	    $this["editForm"]->setDefaults($row);
            //$this["editForm"]->getElementPrototype()->id($this->uniqueId);
	    $this["editForm"]["text"]->setAttribute("id", "ckeditor".$this->getUniqueId());
	}
        else{
            dump("(".get_class($this).") text id: $this->id v db neexistuje");
        }
	
	$this->template->cmpId = $this->getUniqueId();
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
//        $this->setTemplate();
	
	$this->renderEdit();
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
        if($this->presenter->user->isAllowed("sprava-obsahu")){
	    $queryStr = "UPDATE polozky_menu SET poradi = case id ";
	    foreach($postParams["menuItems"] as $itemId){
		$poradi = array_search($itemId, $postParams["menuItems"]);
		$queryStr .= "when $itemId then $poradi ";
	    }
	    $arrValues = implode(",", $postParams["menuItems"]);
	    $queryStr .= "end WHERE id in ($arrValues)";
	    
	    $dbCon = $this->presenter->context->getService("database.default");
	    $result = $dbCon->query($queryStr);
	    
	    if ($this->presenter->isAjax()){
		$this->redrawControl();
	    }
	}
    }
}

class MenuCmpFactory extends \App\Components\BaseCmp\BaseCmpFactory{    
    /** @inject @var Model\Menu */
    public $modelMenu;
    /** @inject @var Model\PolozkyMenu */
    public $modelPolozkyMenu;
    
    const inQuickAddMenu = false;
    const title = "Menu";
}
