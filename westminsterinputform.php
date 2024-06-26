<?php $title="Westminster-style parliament diagram generator"; ?>
<?php require('header.php'); ?>

<script type='text/javascript'>
$(document).ready(function() {
	jscolor.installByClassName("jscolor");
});

//Generate random color, based on http://stackoverflow.com/questions/1484506
function getRandomColor() {
        var letters = '0123456789ABCDEF'.split('');
        var color = ''; // In my case, I don't want the leading #
        for (var i = 0; i < 6; i++ ) {
                color += letters[Math.floor(Math.random() * 16)];
        }
        return color;
}

function CallDiagramScript(){
        // Create request string: this is the request that is passed to the python script.
        var requeststring="";
        // Create legend string: this is a Wikipedia markup legend that can be pasted into an article.
        var legendstring="";
        var legendname  ="";
        var legendnum   ="";
        var spotradius  ="0";
        var spotspacing ="0";
        var wingrows    ="0";
        var centercols  ="0";
        var fullwidth   ="0";
        var cozy        ="0";
        var autospeaker ="0";
        var partylist   = new Array();
        var bigparty    = 1; //this variable will hold the index of the party with the biggest support: used for creating an auto-speaker spot.
        $( "input" ).each( function() {
                if(this.name.match( /^radius/ )){
                  spotradius=parseFloat(this.value);
                  if(spotradius<0){spotradius=0}
                }
                if(this.name.match( /^spacing/ )){
                  spotspacing=parseFloat(this.value);
                  if(spotspacing<0){spotspacing=0}
                  if(spotspacing>0.99){spotspacing=0.99} //don't allow spots of size 0.
                }
                if(this.name.match( /^wingrows/ )){
                  wingrows=parseInt(this.value);
                  if(wingrows<0){wingrows=0}
                }
                if(this.name.match( /^centercols/ )){
                  centercols=parseInt(this.value);
                  if(centercols<0){centercols=0}
                }
                if(this.name.match( /^fullwidth/ )){
                  if(this.checked){fullwidth="1"}
                }
                if(this.name.match( /^cozy/ )){
                  if(this.checked){cozy="1"}
                }
                if(this.name.match( /^autospeaker/ )){
                  autospeaker=parseInt(this.value);
                  if(autospeaker<0){autospeaker=0}
                }
                if(this.name.match( /^Name/ )){
                  partylist[/[0-9]+$/.exec(this.name)[0]]={Name: this.value };
                }
                //Don't allow parties without delegates: if we have a number field, make the value at least 1.
                //It's a bit of a hack, but shouldn't be much of a limitation.
                if(this.name.match( /^Number/ )){
                  if(this.value < 1){this.value = 1;};
                  partylist[/[0-9]+$/.exec(this.name)[0]]['Num']=this.value;
                }
                //If we are processing a colour string, add a # before the hex values.
                if(this.name.match( /^Color/ )){
                  partylist[/[0-9]+$/.exec(this.name)[0]]['Color']=this.value;
                }
        });
        $( "select" ).each( function() {
                if(this.name.match( /^Group/ )){
                  partylist[/[0-9]+$/.exec(this.name)[0]]['Group']=this.value;
                }
        });
        var arrayLength = partylist.length;
        requeststring += "option.radius, "+spotradius.toFixed(2)+"; ";
        requeststring += "option.spacing, "+spotspacing.toFixed(2)+"; ";
        requeststring += "option.wingrows, "+wingrows+"; ";
        requeststring += "option.centercols, "+centercols+"; ";
        requeststring += "option.fullwidth, "+fullwidth+"; ";
        requeststring += "option.cozy, "+cozy+"; ";
        for (var i = 1; i < arrayLength; i++) {
          if(partylist[i]) {
                //Find the biggest party while going through the list - this is
                //such a cheap operation that I'm not going to bother to check
                //each time whether "autospeaker" is checked.
                //
                //if bigparty is pointing to a non-null member of the party list, check whether the current member has larger support, and if so point to it instead.
                if(partylist[bigparty]) {
                  if(parseInt(partylist[i]['Num']) > parseInt(partylist[bigparty]['Num'])) {
                    bigparty=i;
                  }
                }
                //if bigparty is not pointing to a non-null member of the party list, point to the current member instead.
                else {
                  bigparty=i;
                }
                requeststring += partylist[i]['Name'];
                requeststring += ', ';
                requeststring += partylist[i]['Num'];
                requeststring += ', ';
                requeststring += partylist[i]['Group'];
                requeststring += ', #';
                requeststring += partylist[i]['Color'];
                if ( i < (arrayLength - 1)){ requeststring += '; '}
                if (partylist[i]['Num'] == 1){
                  if (partylist[i]['Group'] != "head"){legendstring += "{{legend|#" + partylist[i]['Color'] +"|" + partylist[i]['Name'] +": 1 seat}} "}
                }
                else {
                  if (partylist[i]['Group'] != "head"){legendstring += "{{legend|#" + partylist[i]['Color'] +"|" + partylist[i]['Name'] +": "+ partylist[i]['Num']+" seats}} "}
                }
          }
        }
        if(autospeaker){
            requeststring += '; ';
            requeststring += partylist[bigparty]['Name'];
            requeststring += ', ';
            requeststring += autospeaker;
            requeststring += ', head, #';
            requeststring += partylist[bigparty]['Color'];
        }
            if(arrayLength){
              //Now post the request to the script which actually makes the diagram.
              $.ajax({
                      type: "POST",
                      url: "westminster.py",
                      data: {inputlist: requeststring },
              }).done( function(data,status){
		      data=data.trim();
                      var postcontainer = document.getElementById("postcontainer"); //This will get the first node with id "postcontainer"
                      var postparent = postcontainer.parentNode; //This will get the parent div that contains all the graphs
                      var newpost = document.createElement("div"); //This is the new postcontainer that will hold our stuff.
                      postparent.insertBefore(newpost, postcontainer);
                      newpost.setAttribute("id", "postcontainer");
                      //Now add the svg image to the page
                      var img = document.createElement("img");
                      img.src = data;
                      newpost.appendChild(img);
                      //and a linebreak
                      newpost.appendChild(document.createElement("br"));
                      //Add a link with the new diagram
                      var a = document.createElement('a');
                      var linkText = document.createTextNode("Click to download your SVG diagram.");
                      a.appendChild(linkText);
                      a.title = "SVG diagram";
                      a.href = data;
                      a.download = data;
                      newpost.appendChild(a);
                      //and a linebreak
                      newpost.appendChild(document.createElement("br"));
                      //Now add the legend template text with the party names, colours and support.
                      var newtext = document.createTextNode("Legend template for use in Wikipedia:");
                      newpost.appendChild(newtext);
                      newpost.appendChild(document.createElement("br"));
                      newtext = document.createTextNode(legendstring);
                      newpost.appendChild(newtext);
                      newpost.appendChild(document.createElement("br"));
              });
              console.log(requeststring);
              console.log(legendstring);
            }
}

function addParty(){
        // Party list <div> where dynamic content will be placed
        var partylistcontainer = document.getElementById("partylistcontainer");
        //New party's number: one more than the largest party number so far:
        i=0;
        $( "div" ).each( function() {
            if(this.id.match( /^party[0-9]+$/ )){
                i=Math.max(i, parseInt(/[0-9]+$/.exec(this.id)[0] ));
              }
          }
        )
        i++;
        var newpartydiv=document.createElement('div');
        newpartydiv.id="party" + i;
        partylistcontainer.appendChild(newpartydiv);
        //Party name label
        var partytitle=document.createElement('div');
        partytitle.className = 'left';
        partytitle.innerHTML = "Party " + i + " name";
        newpartydiv.appendChild(partytitle);
        //Party name input control
        var input=document.createElement('div');
        input.innerHTML = '<input class="right" type="text" name="Name' +  i + '"   value= "Party ' +  i + '" >'
        newpartydiv.appendChild(input);
        //Party support name tag
        var partysupport=document.createElement('div');
        partysupport.className = 'left';
        partysupport.innerHTML = "Party " + i + " delegates";
        newpartydiv.appendChild(partysupport);
        //Party support input control
        var input=document.createElement('div');
        input.innerHTML = '<input class="right" type="number" name="Number' +  i + '"   value= "' +  i + '" >';
        newpartydiv.appendChild(input);
        //Party group name tag
        var partygroup=document.createElement('div');
        partygroup.className = 'left';
        partygroup.innerHTML = "Party " + i + " group";
        newpartydiv.appendChild(partygroup);
        //Party group input control
        var input=document.createElement('div');
        input.innerHTML = '<select class="right" name="Group' +  i + '" >\n'+
          '  <option value="left">Left</option>\n'+
          '  <option value="right">Right</option>\n'+
          '  <option value="center">Cross-bench</option>\n'+
          '  <option value="head">Speaker</option>\n'+
          '</select>';
        newpartydiv.appendChild(input);
        //Party color name tag
        var partycolor=document.createElement('div');
        partycolor.className = 'left';
        partycolor.innerHTML = "Party " + i + " color";
        newpartydiv.appendChild(partycolor);
        //Party color input control
        var input=document.createElement('div');
        input.innerHTML = '<input class="right jscolor" type="text" name="Color' +  i + '" value= "' +  getRandomColor() + '" >'
        newpartydiv.appendChild(input);
        var delbutton=document.createElement('div');
        delbutton.className = 'button deletebutton';
        delbutton.innerHTML = "Delete party " + i;
        delbutton.setAttribute("onClick", "deleteParty(" + i + ")");
        newpartydiv.appendChild(delbutton);
        //Add a newline
        newpartydiv.appendChild(document.createElement("br"));
        //$( "input[name=Color" + i + "]").addClass('color'); /* no longer needed because I'm writing the innerHTML
        jscolor.installByClassName("jscolor");
}
function deleteParty(i){
  var delparty = document.getElementById("party"+i);
  var partylistcontainer = document.getElementById("partylistcontainer");
  partylistcontainer.removeChild(delparty);
}
</script>

<div class=block>
  <div class="notice">This tool is in Beta testing. Please help to test it, but if you use it to generate diagrams for Wikipedia, please make sure that you confirm with other editors what the consensus is on which layout and settings to use.</div><br>
  This is a tool to generate Westminster-style parliament diagrams, with a house composed of a left bench, a right bench, a cross-bench group and a "head" - for example Speaker of Parliament.<br>
</div>
<div class=block>
  To use this tool, fill in the name and support of each party in the legislature, clicking "add party" whenever you need to add a new party.
  Then click "Make my diagram", and a link will appear to your SVG diagram. You can then freely download and use the diagram.
  To use it in Wikipedia, I recommend uploading it to Wikimedia Commons.
  If you do upload it, I recommend adding it to the <a href="https://commons.wikimedia.org/wiki/Category:Election_apportionment_diagrams">election apportionment diagrams</a> category.<br>
</div>
<div class=block>
  <div id="spotshapeoptioncontainer" class=block>
    <h3>Spot shape options</h3>
    Try out different values of corner radius to round out the blocks (negative
    values will be set to 0, and anything over 0.5 is a circle). Many Wikipedia
    diagrams use sharp-cornered squares (radius 0).<br>
    <div class="left">Corner radius</div><input class="right" type="number" name="radius"  value = 1.0 ><br>
    Try out different values of spot spacing to separate the blocks (only
    values between 0 and 0.99 will be used: 0 means the spots touch; 1 will
    give invisible spots.)</br>
    <div class="left">Spot spacing </div><input class="right" type="number" name="spacing" value = 0.1 ><br>
  </div>
  <div id="layoutoptioncontainer" class=block>
    <h3>Layout options</h3>
    To use the automatic layout, leave "wing rows" and "cross-bench columns" at
    0, otherwise use them to specify the number of rows in the left and right
    wings of the diagram, and the number of columns in the cross-bench section,
    respectively.</br>
    <div class="left">Wing rows           </div><input class="right" type="number" name="wingrows" value = 0 ><br>
    <div class="left">Cross-bench columns </div><input class="right" type="number" name="centercols" value = 0 ><br>
    <br>
    Would you like the left and right wings to use the full width of the
    diagram, by making one wing thinner than the other?<br>
    <input type="checkbox" class="left" name="fullwidth" value="fullwidth" >Use full width <br>
    <br>
    Would you like to allow parties to share a column in the diagram?<br>
    <input type="checkbox" class="left" name="cozy" value="cozy" checked>Let parties share columns<br>
    <br>
    To create a group of spots for "speakers" or similar functions, you can add parties with the "group" set to "Speaker". To automatically create one or more "speaker" spots with the colour of the largest party, without creating them as separate parties, you can just type a number in the checkbox below:<br>
    <div class="left">Speaker spots</div><input class="right" type="number" name="autospeaker" value = 0 ><br>
  </div>
  <div id="partylistcontainer" class=block>
    <h3>List of parties</h3>
    <div id="party1">
      <div class="left">Party 1 name      </div><input class="right" type="text"   name="Name1"   value= "Party 1" ><br>
      <div class="left">Party 1 delegates </div><input class="right" type="number" name="Number1" value = 1        ><br>
      <div class="left">Party 1 group     </div>
      <select class="right" name="Group 1">
        <option value="left">Left</option>
        <option value="right">Right</option>
        <option value="center">Cross-bench</option>
        <option value="head">Speaker</option>
      </select><br>
      <div class="left">Party 1 color </div><input class="right jscolor" type="text" name="Color1" value= AD1FFF ><br>
      <div class="button deletebutton" onclick="deleteParty(1)">Delete party 1</div><br>
      <br>
    </div>
  </div>
</div>
<div class=button onclick="addParty()">
  Add a party
</div>
<div class=button onclick="CallDiagramScript()">
  Make my diagram
</div>
<div class="block">
  <div id="postcontainer">
    <br>
  </div>
</div>

<?php require('footer.php'); ?>
