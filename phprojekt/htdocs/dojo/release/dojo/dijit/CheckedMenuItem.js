/*
	Copyright (c) 2004-2011, The Dojo Foundation All Rights Reserved.
	Available via Academic Free License >= 2.1 OR the modified BSD license.
	see: http://dojotoolkit.org/license for details
*/


dojo._hasResource["dijit.CheckedMenuItem"]||(dojo._hasResource["dijit.CheckedMenuItem"]=!0,dojo.provide("dijit.CheckedMenuItem"),dojo.require("dijit.MenuItem"),dojo.declare("dijit.CheckedMenuItem",dijit.MenuItem,{templateString:dojo.cache("dijit","templates/CheckedMenuItem.html",'<tr class="dijitReset dijitMenuItem" dojoAttachPoint="focusNode" role="menuitemcheckbox" tabIndex="-1"\n\t\tdojoAttachEvent="onmouseenter:_onHover,onmouseleave:_onUnhover,ondijitclick:_onClick">\n\t<td class="dijitReset dijitMenuItemIconCell" role="presentation">\n\t\t<img src="${_blankGif}" alt="" class="dijitMenuItemIcon dijitCheckedMenuItemIcon" dojoAttachPoint="iconNode"/>\n\t\t<span class="dijitCheckedMenuItemIconChar">&#10003;</span>\n\t</td>\n\t<td class="dijitReset dijitMenuItemLabel" colspan="2" dojoAttachPoint="containerNode,labelNode"></td>\n\t<td class="dijitReset dijitMenuItemAccelKey" style="display: none" dojoAttachPoint="accelKeyNode"></td>\n\t<td class="dijitReset dijitMenuArrowCell" role="presentation">&nbsp;</td>\n</tr>\n'),
checked:!1,_setCheckedAttr:function(a){dojo.toggleClass(this.domNode,"dijitCheckedMenuItemChecked",a);dijit.setWaiState(this.domNode,"checked",a);this._set("checked",a)},onChange:function(){},_onClick:function(a){this.disabled||(this.set("checked",!this.checked),this.onChange(this.checked));this.inherited(arguments)}}));