<?php

abstract class SMWDescriptionFactory{

	abstract public function CreateSMWDescription();
}

class SMWConceptFactory extends SMWDescriptionFactory{

	public function CreateSMWDescription(){

	}

}

class SMWNamespaceFactory extends SMWDescriptionFactory{

	public function CreateSMWDescription(){

	}

}

class SMWValueFactory extends SMWDescriptionFactory {

	public function CreateSMWDescription(){

	}

}

class SMWConjunctionFactory extends  SMWDescriptionFactory{

	public function CreateSMWDescription(){
	
	}
}

class SMWDisjunctionFactory extends  SMWDescriptionFactory{

	public function CreateSMWDescription(){
	
	}
}

class SMWSomePropertyFactory extends  SMWDescriptionFactory{

	public function CreateSMWDescription(){
	
	}
}

class SMWThingDescriptionFactory extends SMWDescriptionFactory{

	public function CreateSMWDescription(){
	
	}
}

