function cctoken(){}
cctoken.prototype.credit = function(){
	jQuery("#mer_merrco-token-form").slideUp();
	jQuery("#wc-mer_merrco-cc-form").slideDown();	
}
cctoken.prototype.token = function(){
	jQuery("#mer_merrco-token-form").slideDown();
	jQuery("#wc-mer_merrco-cc-form").slideUp();
	jQuery("input:radio[name=mer_merrco-token-number]:first").attr('checked', true);
}
var newCredit = new cctoken();
function merrcotoken() {
	newCredit.token();
}
function merrcocc() {
	newCredit.credit();	
}

