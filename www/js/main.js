/*! ikofein.cz 2021-05-02 19:08:01
 *  Copyright (c) 2021 Jakub Bouček (https://www.jakub-boucek.cz/) */

!function(e){const a=new Date,t=e("html").attr("lang");a.setTime(a.getTime()+2592e6);const l="; expires="+a.toUTCString();document.cookie="lang="+t+l+"; path=/"}(jQuery),$(".gallery").magnificPopup({delegate:"a",type:"image",gallery:{enabled:!0},removalDelay:300,mainClass:"gallery-popup"});