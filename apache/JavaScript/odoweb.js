
function ShowUpload() {
	var fileupload = document.getElementById("imafile");
	
	if( fileupload.disabled ) {
		fileupload.disabled = false;
		document.getElementById("imnotafile").disabled = true;
	}
	else {
		fileupload.value = "";
		fileupload.disabled = true;
		document.getElementById("imnotafile").disabled = false;
	}
}

function HeadFootClick() {
	var headchk = document.getElementById("imaheader");
	var footchk = document.getElementById("imafooter");

	if( headchk.checked == 1 ) {
		footchk.checked = false;
		footchk.disabled = true;
	} else {
		footchk.disabled = false;
	}

	if( footchk.checked == 1 ) {
		headchk.checked = false;
		headchk.disabled = true;
	} else {
		headchk.disabled = false;
	}	
}

function GroupReadClick(groupid) {
	var readme = "read"+groupid;
	var writeme = "write"+groupid;
	if( document.getElementById(writeme).disabled ) {
		document.getElementById(writeme).disabled = false;
	} else {
		document.getElementById(writeme).checked = false;
		document.getElementById(writeme).disabled = true;
	}

}

function ObjectReadClick(ObjID) {
	var tableObj = "ObjTbl"+ObjID;
	var readme = "Allow"+ObjID;
	if( !document.getElementById(readme).checked ) {
		var TempEl = document.getElementById(tableObj);
		TempEl.style.display = "none";
	} else {
		var TempEl = document.getElementById(tableObj);
		TempEl.style.display = "";
	}

}

function ClearChecksinTable(RecordCount) {
	var addme = "read";
	for (var i=0; i<RecordCount; i++){
		addme = "read"+i;
		document.getElementById(addme).checked = false;
	}
}


var CurMenuItem=0;
function SelectMenuItem(menuitem) {
	var item = document.getElementById(menuitem);

	if(CurMenuItem != 0) {
		var previtem = document.getElementById(CurMenuItem);
		previtem.style.fontWeight = 'normal';
	}

	item.style.fontWeight = 'bold';
	CurMenuItem = menuitem;
	
	if(document.forms["EditMenu"] != null) {
		document.forms["EditMenu"].elements["SelectedMenuItem"].value = menuitem;
	}

	if(document.forms["DeleteMenu"] != null) {
		document.forms["DeleteMenu"].elements["SelectedMenuItem"].value = menuitem;
	}

	if(document.forms["MoveMenu"] != null) {
		document.forms["MoveMenu"].elements["SelectedMenuItem"].value = menuitem;
	}

	if(document.forms["NewMenu"] != null) {
		document.forms["NewMenu"].elements["SelectedMenuItem"].value = menuitem;
	}

	if(document.forms["UpMenu"] != null) {
		document.forms["UpMenu"].elements["SelectedMenuItem"].value = menuitem;
	}

	if(document.forms["DownMenu"] != null) {
		document.forms["DownMenu"].elements["SelectedMenuItem"].value = menuitem;
	}

}

var CurEPage=1;
function ShowEvents(pageid, maxrow, lastpage) {
	var temptbl = document.getElementById("tblSongs");
	temptbl.style.display = "none";
	temptbl = document.getElementById("SPageRow");
	temptbl.style.display = "none";
	temptbl = document.getElementById("tblAlbums");
	temptbl.style.display = "none";
	temptbl = document.getElementById("APageRow");
	temptbl.style.display = "none";
	temptbl = document.getElementById("tblEvents");
	temptbl.style.display = "";
	temptbl = document.getElementById("EPageRow");
	temptbl.style.display = "";

	var NewPage = "LinkIDE"+pageid;
	var TempPage = "";
	var TopRowOfPage = 0;
	var BotRowOfPage = 15;
	var TempRow = "";

	if((pageid < -2)||(pageid>lastpage)) {
		alert("Page selected is out of range:"+pageid);
		return;
	}

	if(lastpage > 1) {
		for (var i=1; i<lastpage+1; i++){
			TempPage = "LinkIDE"+i;
			var TempEl = document.getElementById(TempPage);
			if(TempEl.style.fontWeight == "bold") {
				CurEPage = i;
			}
			TempEl.style.fontWeight = "";
		}
	} else {
		CurEPage = 1;

	}

	if(pageid==-1) {
		if(CurEPage==1) {
			var TempEl = document.getElementById("LinkIDE1");
			TempEl.style.fontWeight = "bold";
			alert("You are already at the first page.");
			return;
		} else {
			pageid=CurEPage-1;
		}
	}

	if(pageid==-2) {
		if(CurEPage==lastpage) {
			var TempEl = document.getElementById("LinkIDE"+lastpage);
			TempEl.style.fontWeight = "bold";
			alert("You are already at the last page.");
			return;
		} else {
			pageid=CurEPage+1;
		}
	}

	TopRowOfPage = (pageid-1)*15;
	BotRowOfPage = TopRowOfPage+BotRowOfPage-1;
	for (var i=0; i<maxrow; i++){
		TempRow = "Erownum"+i;
		var TempEl = document.getElementById(TempRow);
		if(i>=TopRowOfPage && i<=BotRowOfPage) {
			TempEl.style.display = "";
		} else {
			TempEl.style.display = "none";
		}
		
	}
	CurEPage=pageid;
	TempPage="LinkIDE"+CurPage;
	if(lastpage > 1) {
		var TempEl = document.getElementById(TempPage);
		TempEl.style.fontWeight = "bold";
	}
	
}

var CurAPage=1;
function ShowAlbums(pageid, maxrow, lastpage) {
	var temptbl = document.getElementById("tblSongs");
	temptbl.style.display = "none";
	temptbl = document.getElementById("SPageRow");
	temptbl.style.display = "none";
	temptbl = document.getElementById("tblEvents");
	temptbl.style.display = "none";
	temptbl = document.getElementById("EPageRow");
	temptbl.style.display = "none";
	temptbl = document.getElementById("tblAlbums");
	temptbl.style.display = "";
	temptbl = document.getElementById("APageRow");
	temptbl.style.display = "";

	var NewPage = "LinkIDA"+pageid;
	var TempPage = "";
	var TopRowOfPage = 0;
	var BotRowOfPage = 15;
	var TempRow = "";

	if((pageid < -2)||(pageid>lastpage)) {
		alert("Page selected is out of range:"+pageid);
		return;
	}

	if(lastpage > 1) {
		for (var i=1; i<lastpage+1; i++){
			TempPage = "LinkIDA"+i;
			var TempEl = document.getElementById(TempPage);
			if(TempEl.style.fontWeight == "bold") {
				CurAPage = i;
			}
			TempEl.style.fontWeight = "";
		}
	} else {
		CurAPage=1;
	}

	if(pageid==-1) {
		if(CurAPage==1) {
			var TempEl = document.getElementById("LinkIDA1");
			TempEl.style.fontWeight = "bold";
			alert("You are already at the first page.");
			return;
		} else {
			pageid=CurAPage-1;
		}
	}

	if(pageid==-2) {
		if(CurAPage==lastpage) {
			var TempEl = document.getElementById("LinkIDA"+lastpage);
			TempEl.style.fontWeight = "bold";
			alert("You are already at the last page.");
			return;
		} else {
			pageid=CurAPage+1;
		}
	}

	TopRowOfPage = (pageid-1)*15;
	BotRowOfPage = TopRowOfPage+BotRowOfPage-1;
	for (var i=0; i<maxrow; i++){
		TempRow = "Arownum"+i;
		var TempEl = document.getElementById(TempRow);
		if(i>=TopRowOfPage && i<=BotRowOfPage) {
			TempEl.style.display = "";
		} else {
			TempEl.style.display = "none";
		}
		
	}
	CurAPage=pageid;
	TempPage="LinkIDA"+CurPage;
	if(lastpage > 1) {
		var TempEl = document.getElementById(TempPage);
		TempEl.style.fontWeight = "bold";
	}
	
}



var CurSPage=1;
function ShowSongs(pageid, maxrow, lastpage) {
	var temptbl = document.getElementById("tblAlbums");
	temptbl.style.display = "none";
	temptbl = document.getElementById("APageRow");
	temptbl.style.display = "none";
	temptbl = document.getElementById("tblEvents");
	temptbl.style.display = "none";
	temptbl = document.getElementById("EPageRow");
	temptbl.style.display = "none";
	temptbl = document.getElementById("tblSongs");
	temptbl.style.display = "";
	temptbl = document.getElementById("SPageRow");
	temptbl.style.display = "";
	
	var NewPage = "LinkIDS"+pageid;
	var TempPage = "";
	var TopRowOfPage = 0;
	var BotRowOfPage = 15;
	var TempRow = "";

	if((pageid < -2)||(pageid>lastpage)) {
		alert("Page selected is out of range:"+pageid);
		return;
	}

	if(lastpage > 1) {
		for (var i=1; i<lastpage+1; i++){
			TempPage = "LinkIDS"+i;
			var TempEl = document.getElementById(TempPage);
			if(TempEl.style.fontWeight == "bold") {
				CurSPage = i;
			}
			TempEl.style.fontWeight = "";
		}
	} else {
		CurSPage = 1;
	}

	if(pageid==-1) {
		if(CurSPage==1) {
			var TempEl = document.getElementById("LinkIDS1");
			TempEl.style.fontWeight = "bold";
			alert("You are already at the first page.");
			return;
		} else {
			pageid=CurSPage-1;
		}
	}

	if(pageid==-2) {
		if(CurSPage==lastpage) {
			var TempEl = document.getElementById("LinkIDS"+lastpage);
			TempEl.style.fontWeight = "bold";
			alert("You are already at the last page.");
			return;
		} else {
			pageid=CurSPage+1;
		}
	}

	TopRowOfPage = (pageid-1)*15;
	BotRowOfPage = TopRowOfPage+BotRowOfPage-1;
	for (var i=0; i<maxrow; i++){
		TempRow = "Srownum"+i;
		var TempEl = document.getElementById(TempRow);
		if(i>=TopRowOfPage && i<=BotRowOfPage) {
			TempEl.style.display = "";
		} else {
			TempEl.style.display = "none";
		}
		
	}
	CurSPage=pageid;
	TempPage="LinkIDS"+CurPage;
	if(lastpage > 1) {
		var TempEl = document.getElementById(TempPage);
		TempEl.style.fontWeight = "bold";
	}
	
}


var CurPage=1;
function ChangePage(pageid, maxrow, lastpage) {
	var NewPage = "LinkID"+pageid;
	var TempPage = "";
	var TopRowOfPage = 0;
	var BotRowOfPage = 15;
	var TempRow = "";

	if((pageid < -2)||(pageid>lastpage)) {
		alert("Page selected is out of range:"+pageid);
		return;
	}

	for (var i=1; i<lastpage+1; i++){
		TempPage = "LinkID"+i;
		var TempEl = document.getElementById(TempPage);
		if(TempEl.style.fontWeight == "bold") {
			CurPage = i;
		}
		TempEl.style.fontWeight = "";
		
	}

	if(pageid==-1) {
		if(CurPage==1) {
			var TempEl = document.getElementById("LinkID1");
			TempEl.style.fontWeight = "bold";
			alert("You are already at the first page.");
			return;
		} else {
			pageid=CurPage-1;
		}
	}

	if(pageid==-2) {
		if(CurPage==lastpage) {
			var TempEl = document.getElementById("LinkID"+lastpage);
			TempEl.style.fontWeight = "bold";
			alert("You are already at the last page.");
			return;
		} else {
			pageid=CurPage+1;
		}
	}

	TopRowOfPage = (pageid-1)*15;
	BotRowOfPage = TopRowOfPage+BotRowOfPage-1;
	for (var i=0; i<maxrow; i++){
		TempRow = "rownum"+i;
		var TempEl = document.getElementById(TempRow);
		if(i>=TopRowOfPage && i<=BotRowOfPage) {
			TempEl.style.display = "";
		} else {
			TempEl.style.display = "none";
		}
		
	}
	CurPage=pageid;
	TempPage="LinkID"+CurPage;
	var TempEl = document.getElementById(TempPage);
	TempEl.style.fontWeight = "bold";

}

function ShowOtherTableRows(MySelect) {
	var i = MySelect.length;
	var tblRow;
	
	for (var j=1; j<i; j++) {
		//first hide everything
		tblRow = document.getElementById(MySelect.options[j].value);
		tblRow.style.display = "none";

	}
	
	if(MySelect.selectedIndex > 0) {
		tblRow = document.getElementById(MySelect.options[MySelect.selectedIndex].value);
		tblRow.style.display = "";
	}

}


function leftmOpenClose(menuitem, callingItem) {
		var unorderedList = document.getElementById(menuitem);
		var anchorItem = document.getElementById(callingItem);
		
		if(unorderedList.style.display == "none") {
			unorderedList.style.display = "block";
			anchorItem.style.backgroundImage = "url(images/expanded.gif)";
		} else {
			unorderedList.style.display = "none";
			anchorItem.style.backgroundImage = "url(images/collapsed.gif)";
		}
}
