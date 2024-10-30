/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


(function($) {
	$(document).ready(function(){
            
	$('.hasTooltip').each(function() { // Notice the .each() loop, discussed below
    $(this).click(function(e){
        e.preventDefault();
        if($(this).next('.tooltiptext').is(':visible')){
            console.log('visible');
            $('.tooltiptext:visible').hide()
        
        }else{
            console.log('hidden')
            $('.tooltiptext:visible').hide()
        $(this).next('.tooltiptext').show();
        }
        
    })
});
        })

	
})( jQuery );