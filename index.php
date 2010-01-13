<?php

/*

 FATMAN, the FreeAgent Time Manager $version - Version 1.0

 Copyright (c) 2009, Gerben Meijer (infernix@infernix.net)
 All rights reserved.

 Redistribution and use in source and binary forms, with or without
 modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the organization nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

 THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
 DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

include('config.inc.php');
$version = 1.0;

// Let's begin!


// Lets see if we have a Freeagent company name in our GET variables. If not, just print the help page and return.
if(!isset($company)) {
	if(isset($_GET['company']) && !empty($_GET['company'])) {
		$company = $_GET['company'];
	} else {
		$title = "FreeAgent Time Manager $version - Welcome!";
		$content = file_get_contents("help.html");
		browserTest();
		$all = file_get_contents("template.html");
		$all = str_replace(array("%%title%%", "%%content%%"), array($title, $content), $all);
		echo $all;
		exit;
	}
}

// Possible application states:
//    State			$_POST['action]'	What to do
// 1) Logged out/no AUTH	Anything		Show program help
// 1) Clocked in		Nothing			Show clock-out options;
// 2) Clocked in		Cancel			Cancel clock-in & show clock-in options;
// 3) Clocked in		Clockout		Clock out at given time & show clock-in options;
// 4) Clocked out		Nothing			Show recent tasks, show contacts
// 5) Clocked out		Projects		Show contacts, show projects, create new project
// 6) Clocked out		Tasks			Show contacts, show projects, show tasks + clock-in, create new task + clock-in
// 5) Clocked out		Clockin			Clock in at given time & show clock-out options
// 6) Anything			History			Show logged timeslip history
// 8) Error			Anything		Show error state



// First, we need to determine if we're logged in or not.
// If not, we have just one state, which is "Show program help"
// Note that with a pre-configured username and password, we're always considered to be logged in.

// check if we have preconfigured user/pass
// if not, lets get it through HTTP authentication
if(!isset($fac_username) && !isset($fac_password)) {
	// Nope, lets ask the user for it
	if (!isset($_SERVER['PHP_AUTH_USER'])) {
		header('WWW-Authenticate: Basic realm="FreeAgent Time Machine for '.$company.'.freeagentcentral.com"');
		header('HTTP/1.0 401 Unauthorized');
		
		// If we get this far, that means the user didn't log in or failed to do so
		$title = "FreeAgent Time Manager $version - Not logged in";
		$content = file_get_contents("help.html");
		browserTest();
		$all = file_get_contents("template.html");
		$all = str_replace(array("%%title%%", "%%content%%"), array($title, $content), $all);
		print $all;
		die();
	} else {
		// If we get to here, that means the user tried to log in.
		$fac_username = $_SERVER['PHP_AUTH_USER'];
		$fac_password = $_SERVER['PHP_AUTH_PW'];
	}
}

// Now lets validate the given credentials by trying to retrieve this users' data from FreeAgentCentral
$facquery = facgetdata("company/users");
if($facquery["status"] == 200) {
	// Looks like we've logged in succesfully. Lets fill the $userlist object
        $userlist = simplexml_load_string($facquery["data"]);
} else {
	// Incorrect credentials or otherwise unable to log in; don't show error state, just throw yet another 401 Unauthorized until people hit cancel
	header('WWW-Authenticate: Basic realm="FreeAgent Time Machine for '.$company.'.freeagentcentral.com"');
	header('HTTP/1.0 401 Unauthorized');

	$title = "FreeAgent Time Manager $version - Incorrect username and/or password";
	$content = "Error, username and password not accepted by FreeAgentCentral.<br>";
	$content .= file_get_contents("help.html");
	browserTest();
	$all = file_get_contents("template.html");
	$all = str_replace(array("%%title%%", "%%content%%"), array($title, $content), $all);
	print $all;
	die();
}


// Now that we validated the credentials, get the userid, companyid, fullname and last login from $userlist
// for the user that matches the email field with the supplied username
foreach ($userlist->user as $user) {
	if($user->email == $fac_username) {
		$userid = (integer)$user->id;
		$companyid = (integer)$user->{'company-id'};
		$fullname = (string)$user->{'first-name'}." ".(string)$user->{'last-name'};
		$lastlog = strtotime((integer)$user->{'last-logged-in-at'});
	}
}


// If we need to create a new project or task, do it now; otherwise the projectlist or tasklist will be outdated.
// Bit of a hack to prevent a browser refresh

if(isset($_POST['pageaction']) && $_POST['pageaction'] == 'NewProject') {
 if (isset($_POST['activecontact']) && !empty($_POST['activecontact'])) {
  if (isset($_POST['newprojectname']) && !empty($_POST['newprojectname'])) {
   $newprojectpost = facpostnewproject($_POST['activecontact'],$_POST['newprojectname']);
   if($newprojectpost['status'] =! 201) {
    $title = "FreeAgent Time Manager $version - Error";
    $content = "<div id='error'>Error while trying to create new project - return code: ".$facquery['status']."</div>";
    browserTest();
    $all = file_get_contents("template.html");
    $all = str_replace(array("%%title%%", "%%content%%"), array($title, $content), $all);
    print $all;
    die();
   } else {
    $activeproject = $newprojectpost['data'];
   }
  }
 }
}

if(isset($_POST['pageaction']) && $_POST['pageaction'] == 'NewTask') {
 if (isset($_POST['activecontact']) && !empty($_POST['activecontact'])) {
  if (isset($_POST['activeproject']) && !empty($_POST['activeproject'])) {
   if (isset($_POST['newtaskname']) && !empty($_POST['newtaskname'])) {
    $newtaskpost = facpostnewtask($_POST['activeproject'],$_POST['newtaskname']);
    if($newtaskpost['status'] =! 201) {
     $title = "FreeAgent Time Manager $version - Error";
     $content = "<div id='error'>Error while trying to create new task - return code: ".$facquery['status']."</div>";
     browserTest();
     $all = file_get_contents("template.html");
     $all = str_replace(array("%%title%%", "%%content%%"), array($title, $content), $all);
     print $all;
     die();
    } else {
     $activetask = $newtaskpost['data']; 
    }
   }
  }
 }
}

// We need $contactlist and $projectlist in just about every case, so get them.
$facquery = facgetdata("contacts");
if($facquery["status"] == 200) {
	$contactlist = simplexml_load_string($facquery["data"]);
} else {
	$title = "FreeAgent Time Manager $version - Error";
	$content = "<div id='error'>Error while trying to retrieve contacts from FreeAgentCentral - return code: ".$facquery['status']."</div>"; 
	browserTest();
	$all = file_get_contents("template.html");
	$all = str_replace(array("%%title%%", "%%content%%"), array($title, $content), $all);
	print $all;
        die();
}

// Ditto.
$facquery = facgetdata("projects");
if($facquery["status"] == 200) {
	$projectlist = simplexml_load_string($facquery["data"]);
} else {
	$title = "FreeAgent Time Manager $version - Error";
	$content = "<div id='error'>Error while trying to retrieve projects from FreeAgentCentral - return code: ".$facquery['status']."</div>"; 
	browserTest();
	$all = file_get_contents("template.html");
	$all = str_replace(array("%%title%%", "%%content%%"), array($title, $content), $all);
	print $all;
        die();
}

// We might not need this all the time, but we might as well load this too.

$timeslipstart = strtotime('-6 weeks');
$timeslipdate = strftime('%Y-%m-%d',$timeslipstart).'_'.strftime('%Y-%m-%d');
$facquery = facgetdata("timeslips?view=".$timeslipdate);
if($facquery["status"] == 200) {
	$timesliplist = simplexml_load_string($facquery["data"]);
} else {
	$title = "FreeAgent Time Manager $version - Error";
	$content = "<div id='error'>Error while trying to retrieve timeslips from FreeAgentCentral - return code: ".$facquery['status']."</div>"; 
	browserTest();
	$all = file_get_contents("template.html");
	$all = str_replace(array("%%title%%", "%%content%%"), array($title, $content), $all);
	print $all;
        die();
}


// Now we determine our state. Check if statefile exists; if so, read its contents and assume we're clocked in. 
// If not, do a quick test so we're sure we can write in the current dir.
$statefile = $statefile.$companyid.$userid;

// Does it exist?
if (!file_exists($statefile)) {
	// If not, can we create it?
	if(!touch($statefile)) {
		// No we can't, show an error
		$title = "FreeAgent Time Manager $version - Error";
		$content = "<div id='error'>Error while trying to create the statefile $statefile - does the web user have write permissions in the script directory?</div>"; 
		browserTest();
		$all = file_get_contents("template.html");
		$all = str_replace(array("%%title%%", "%%content%%"), array($title, $content), $all);
		print $all;
	        die();
	}
	// If we did successfully create it, delete it; it's just a write test we're doing.
	unlink($statefile); 
} else {
	// It exists, so let's load our state. It's stored as a simple xml file.
	$statedata = simplexml_load_file($statefile);
	// Did we load it succesfully?
	if(!$statedata) {
		// No we didn't, show an error
		$title = "FreeAgent Time Manager $version - Error";
		$content = "<div id='error'>Error while trying to load the statefile $statefile - does the web user have read permissions on this file?</div>"; 
		browserTest();
		$all = file_get_contents("template.html");
		$all = str_replace(array("%%title%%", "%%content%%"), array($title, $content), $all);
		print $all;
		die();
	}
}


// See what the submitted form state was
if(isset($_POST['pagestate']) && !empty($_POST['pagestate'])) {
	$pagestate = $_POST['pagestate'];
} else {
	$pagestate = "Nothing";
}



// So, do we have $statedata?
if(isset($statedata)) {

	// If there was no form submitted value for pagestate, set it to Clocked in
	if($pagestate == "Nothing") { 
		$pagestate = "Clockedin";
	// If the submitted form is in a state of clocked-out, but we're really already clocked-in, panic.
	} elseif($pagestate == "Clockedout") {
		die("Failboat: form submitted in a state we already left, old pagestate $pagestate but real state Clockedin");
		// TODO: cleanup
	}

	// Set the variables we will need further along
	$activecontact = (integer)$statedata->contact;
	$activeproject = (integer)$statedata->project;
	$activetask = (integer)$statedata->task;
	$activetaskname = (string)$statedata->taskname;
	$clockintime = (integer)$statedata->clockintime;

	// Get the contact name from our $contactlist xml by finding the $activecontact id 
	foreach ($contactlist->contact as $contact) {
		if($contact->id == $activecontact) { 
			if(isset($contact->{'organisation-name'})) {
			 	$activecontactname = (string)$contact->{'organisation-name'};
			} else {
			 	$activecontactname = (string)$contact->{'first-name'}." ".(string)$contact->{'last-name'};
			}
		}
	}

	// Get project name from our $projectlist xml by finding the $activeproject id
	foreach ($projectlist->project as $project) {
		if($project->id == $activeproject) {
			$activeprojectname = (string)$project->name; 
		}
	}

	// In case we do clock out, get the clockout time
	if(isset($_POST['clockouttime']) && !empty($_POST['clockouttime'])) {
		$clockouttime 	= strtotime($_POST['clockouttime']);
	}

} else {
	// We don't have state so we'll set the variables from $_POST

	// If there was no form submitted value for pagestate, set it to Clocked in
	if($pagestate == "Nothing") { 
		$pagestate = "Clockedout";
	// If the submitted form is in a state of clocked-out, but we're really already clocked-in, panic.
	} elseif($pagestate == "Clockedin") {
		die("Failboat: form submitted in a state we already left, old pagestate $pagestate but real state Clockedout");
		// TODO: cleanup
	}

	if(isset($_POST['activetask']) && !empty($_POST['activetask'])) {
		$activetask = $_POST['activetask'];
	}

	if (isset($_POST['activeproject']) && !empty($_POST['activeproject'])) {
		$activeproject = $_POST['activeproject'];
	}

	if (isset($_POST['activecontact']) && !empty($_POST['activecontact'])) {
		$activecontact = $_POST['activecontact'];
	}

	// If a recent task has been selected, set all the variables based on its contents
	if (isset($_POST['recenttask']) && !empty($_POST['recenttask'])) {
		$recenttask = unserialize(base64_decode($_POST['recenttask']));
		$activecontact = $recenttask['contact'];
		$activecontactname = $recenttask['contactname'];
		$activeproject = $recenttask['project'];
		$activeprojectname = $recenttask['projectname'];
		$activetask = $recenttask['id'];
		$activetaskname = $recenttask['name'];
	}
	// In case we clock in, get the clockin time
	if(isset($_POST['clockintime']) && !empty($_POST['clockintime'])) {
		$clockintime = strtotime($_POST['clockintime']);
	}

}

// Now, we must determine the action to take. 

if(isset($_POST['pageaction']) && !empty($_POST['pageaction'])) {
	$pageaction = $_POST['pageaction'];
} else {
	$pageaction = "Nothing";
}



// logging.... TODO

//create almost-empty XML file if it doesn't exist
if (!file_exists($logfile)) {
	$template = '<'.'?xml version="1.0" encoding="UTF-8" '.'?'.'>
<worklog>
	<stats />
	<units />
	<projects />
</worklog>';
	if(!file_put_contents($logfile, $template)) {
		echo "Error creating logfile $logfile";
		die();
	} else {
		chmod($logfile, 0666);
	}
}

$log = simplexml_load_file($logfile);
if(!$log) {
	echo "Error loading $logfile";
	die();
}
//////////////////////////////////////


// Possible application states:
//    State			$_POST['pageaction]'	What to do
// 1) Logged out/no AUTH	Anything		Show program help
// 1) Clocked in		Nothing			Show state, Show clock-out options;
// 2) Clocked in		Cancel			Cancel clock-in & show clock-in options;
// 3) Clocked in		Now			Clock out now() & show clock-in options;
// 3) Clocked in		At			Clock out at $clockouttime & show clock-in options;
// 4) Clocked out		Nothing			Show recent tasks, show contacts
// 5) Clocked out		Projects		Show contacts, show projects, create new project
// 6) Clocked out		Tasks			Show contacts, show projects, show tasks + clock-in, create new task + clock-in
// 5) Clocked out		Clockin			Clock in at given time & show clock-out options
// 6) Anything			History			Show logged timeslip history
// 8) Error			Anything		Show error state


// Now, our main function switches

switch ($pagestate) {
 case 'Clockedin':
  // Now we switch to the action
  switch ($pageaction) {
   // 1) Clocked in                Nothing                 Show state, show clock-out options;
   case 'Nothing':
    $title = "FreeAgent Time Manager $version - Clocked in";
    $content = showheader();
    $content .= show_state();
    $content .= show_clockout();
    break;
   // 2) Clocked in                Cancel                  Cancel clock-in & show clock-in options;
   case 'Cancel':
    $title = "FreeAgent Time Manager $version - Cancel clock in";
    $content = showheader();
    $content .= cancel_clockin();
    $content .= show_recent_tasks();
    $content .= show_contacts();
    $content .= show_projects();
    $content .= show_tasks_clockin();
    break;

   // 3) Clocked in                Clockout                Clock out at given time & show clock-in options;
   case 'At':
    $title = "FreeAgent Time Manager $version - Clock out at ".strftime("%d %b %H:%M",$clockouttime);
    $content = showheader();
    $content .= do_clockout($clockintime,$clockouttime);
    // If we were successful, the statefile is gone and if so, show the clockin form
    if (!file_exists($statefile)) {
     $content .= show_recent_tasks();
     $content .= show_contacts();
     $content .= show_projects();
     $content .= show_tasks_clockin();
    }
    break;
   case 'Now':
    $title = "FreeAgent Time Manager $version - Clock out now";
    $content = showheader();
    $content .= do_clockout($clockintime,time());
    // If we were successful, the statefile is gone and if so, show the clockin form
    if (!file_exists($statefile)) {
     $content .= show_recent_tasks();
     $content .= show_contacts();
     $content .= show_projects();
     $content .= show_tasks_clockin();
    }
    break;
  }
  break;
 case 'Clockedout':
  switch ($pageaction) {
   // 4) Clocked out               Nothing                 Show recent tasks, show contacts
   case 'Nothing':
    $title = "FreeAgent Time Manager $version - Clocked out";
    $content = showheader();
    $content .= show_state();
    $content .= show_recent_tasks();
    $content .= show_contacts();
    break;

   // 5) Clocked out               Projects                Show contacts, show projects, create new project
   case 'Projects':
   case 'NewProject':
    $title = "FreeAgent Time Manager $version - Show projects";
    $content = showheader();
    $content .= show_state();
    //$content .= show_recent_tasks();
    $content .= show_contacts();
    $content .= show_projects();
    break;

   // 6) Clocked out               Tasks                   Show contacts, show projects, show tasks + clock-in, create new task + clock-in
   case 'Tasks':
   case 'NewTask':
    $title = "FreeAgent Time Manager $version - Show tasks";
    $content = showheader();
    $content .= show_state();
    //$content .= show_recent_tasks();
    $content .= show_contacts();
    $content .= show_projects();
    $content .= show_tasks_clockin();
    break;
   
   // 5) Clocked out               Clockin                 Clock in at given time & show clock-out options
   case 'At':
    $title = "FreeAgent Time Manager $version - Clocking in";
    $content = showheader();
    $content .= do_clockin($clockintime);
    $content .= show_clockout();
    break;
   case 'Now':
    $title = "FreeAgent Time Manager $version - Clocking in now";
    $content = showheader();
    $content .= do_clockin(time());
    $content .= show_clockout();
    break;
   case 'RecentTask':
    $title = "FreeAgent Time Manager $version - Selected recent task";
    $content = showheader();
    $content .= show_state();
    $content .= show_recent_tasks();
    $content .= show_contacts();
    $content .= show_projects();
    $content .= show_tasks_clockin();
    break;
  }
 break;
}

if(!isset($content)) {
	// Oops, we slipped through the cracks
	$title = "Hmm, weird...";
	$content = "<div id='error'>";
	$content .= "Hmm, looks like we slipped through the cracks. Here's some debug info: <pre>";
	$content .= "POSTDATA:\n";
	$content .= print_r($_POST,TRUE);
	$content .= "</pre>";
}


$content .= "</div>\n";
browserTest();
$all = file_get_contents("template.html");
$all = str_replace(array("%%title%%", "%%content%%"), array($title, $content), $all);
echo $all;



// -------- Functions follow --------






function do_clockin($clockintime) {
	// Lets clock in
	global $activecontact, $activeproject, $activetask, $statefile, $contactlist, $projectlist;
	
	// Does the state file exist?
	if (!file_exists($statefile)) {
		// It doesn't, so lets gather data and create it

		// get contact name
		foreach ($contactlist->contact as $contact) {
			if($contact->id == $activecontact) { 
				if(isset($contact->{'organisation-name'})) {
				 	$activecontactname = (string)$contact->{'organisation-name'};
				} else {
				 	$activecontactname = (string)$contact->{'first-name'}." ".(string)$contact->{'last-name'};
				}
			}
		}

		// get project name
		foreach ($projectlist->project as $project) {
			if($project->id == $activeproject) { 
				$activeprojectname = (string)$project->name; 
			}
		}

		// get task name, TODO: move taskname data into serialized POST variable to skip API query
		$facquery = facgetdata("projects/".$activeproject."/tasks");
		if($facquery["status"] == 200) {
			$tasklist = simplexml_load_string($facquery["data"]);
		} else {
			print "Error fetching tasks data, active project $activeproject, return code ".$facquery["status"]."\n";
			die();
		}
		foreach ($tasklist->task as $task) {
			if($task->id == $activetask) {
				$activetaskname = (string)$task->name;
			}
		}

		// Lets store this clock-in in our statefile
		$statedata = '<state>';
		$statedata .= '<contact>'.$activecontact.'</contact>';
		$statedata .= '<project>'.$activeproject.'</project>';
		$statedata .= '<task>'.$activetask.'</task>';
		$statedata .= '<taskname>'.$activetaskname.'</taskname>';
		$statedata .= '<clockintime>'.$clockintime.'</clockintime>';
		$statedata .= '</state>';
		$statexml = new SimpleXMLElement($statedata);

		// Lets see if we can write the statefile
		if(!file_put_contents($statefile, $statexml->asXML())) {
			// Nope. TODO: cleanup
			echo "Error writing data to statefile $statefile";
			die();
		} else {
			// Wrote statefile, lets output a div.
			$content = "<div id='clockedin'>";
			$content .= "Clocking in to task <b>$activetaskname</b> in project <b>$activeprojectname</b> for contact <b>$activecontactname</b>.<br />";
			$content .= "<ul><li>Clocked <b>in</b> at <b>".strftime("%a %d %b %H:%M:%S",$clockintime)."</b></li></ul>";
			$content .= "Succesfully wrote state data.<br />";
			$content .= "</div>";
			// Log this clockin TODO
		}
	} else {
	        $content = "<div id='error'>";
		$content .= "Error: trying to clock in while we already are. Clock in not saved!";
	        $content .= "</div>";
	}
	return($content);
}

// End of initialization


function show_state() {
	// Returns a div with the current data from the statefile.
	global $activecontact, $activecontactname, $activeproject, $activeprojectname, $activetask, $activetaskname, $clockintime, $projectlist, $currency, $contactlist, $projectlist, $pagestate;

	switch ($pagestate) {
		case "Clockedin":

		// We should fill these variables if they are still empty
		if(empty($activecontactname)) {
			// get contact name
			foreach ($contactlist->contact as $contact) {
				if($contact->id == $activecontact) { 
					if(isset($contact->{'organisation-name'})) {
					 	$activecontactname = (string)$contact->{'organisation-name'};
					} else {
					 	$activecontactname = (string)$contact->{'first-name'}." ".(string)$contact->{'last-name'};
					}
				}
			}
		}
	
		if(empty($activeprojectname)) {
			// get project name
			foreach ($projectlist->project as $project) {
				if($project->id == $activeproject) { 
					$activeprojectname = (string)$project->name; 
				}
			}
		}
	
		if(empty($activetaskname)) {
			// get task name, TODO: move taskname data into serialized POST variable to skip API query
			$facquery = facgetdata("projects/".$activeproject."/tasks");
			if($facquery["status"] == 200) {
				$tasklist = simplexml_load_string($facquery["data"]);
			} else {
				print "Error fetching tasks data, active project $activeproject, return code ".$facquery["status"]."\n";
				die();
			}
			foreach ($tasklist->task as $task) {
				if($task->id == $activetask) {
					$activetaskname = (string)$task->name;
				}
			}
		}
		
		// Get normal-billing rate for this project
		foreach ($projectlist->project as $project) {
			if($project->id == $activeproject) { 
				$billingrate = (float)$project->{'normal-billing-rate'};
			}
		}
		$hours = calculatehours($clockintime,time());
	
		// Create our content
		// show the clock out options
		// NOTE: if you change the button text, also update the checks for $clockout values!
		$content = "<div id='clockstate'>\n";
		$content .= "Currently clocked in. Details:<br />\n\n";
		$content .= "<ul><li>Contact: ".htmlspecialchars($activecontactname)."</li>\n";
		$content .= "<li>Project: ".htmlspecialchars($activeprojectname).", task ".htmlspecialchars($activetaskname)."</li>\n";
		$content .= "<li>Clock in time: ".strftime("%a %d %b %H:%M:%S %Y",$clockintime).", hours so far: ".$hours."</li>";
		if($billingrate > 0) {
			$totalbill = $billingrate*$hours;
			$content .= "<li>Total bill so far: $totalbill $currency at $billingrate $currency/hour</li>";
		}
		$content .= "</ul></div>\n";
		break;
		
		case "Clockedout":
		// Create our content
		// show the clock out options
		// NOTE: if you change the button text, also update the checks for $clockout values!
		$content = "<div id='clockstate'>\n";
		$content .= "Currently clocked out.<br />\n\n";
		$content .= "</div>\n";
	}
	
	return($content);
}

function show_clockout() {
	// Returns a div with the clock out form

	$content = "<div id='clockoutform'>\n";
	$content .= "Clock out?";
	$content .= "<form action='' method='POST'>\n";
	$content .= "<input type='submit' name='pageaction' value='At'/>\n";
	$content .= "<input type='hidden' name='pagestate' value='Clockedin'/>\n";
	$content .= "<input type='text' name='clockouttime' value='".strftime("%H:%M")."' size='5'/><br />\n";
	$content .= "<input type='submit' name='pageaction' value='Now'/> (at current time)<br />\n";
	$content .= "Or <input type='submit' name='pageaction' value='Cancel'/> this clock-in<br />\n";
	$content .= "</form>\n";
	$content .= "</div>\n";
	
	return($content);
}

function calculatehours($clockintime,$clockouttime) {
	// calculate elapsed time in hours:minutes
	$diff = $clockouttime-$clockintime;
	$hrsDiff = floor($diff/60/60);
	$diff -= $hrsDiff*60*60;
	$minsDiff = floor($diff/60);
	$minsHourFactor = round($minsDiff/60,2);
	$elapsedtime = $hrsDiff+$minsHourFactor;
	return($elapsedtime);
}


function do_clockout($clockintime,$clockouttime) {
	// Looks like we're clocking out
	global $activetask, $activetaskname, $activeproject, $activeprojectname, $activecontact, $activecontactname, $statefile;

	// If the clock out time is before the clock in time, throw an error
	if($clockouttime < $clockintime) {
	        $content = "<div id='error'>";
		$content .= "Error: Clock out time is before clock in time, will <b>NOT<b/> clock out!";
	        $content .= "</div>";
	} else {	
		// Calculate elapsed time
		$elapsedtime = calculatehours($clockintime,$clockouttime);

		// Submit timeslip
		$timeslippost = facposttimeslip($clockintime,$clockouttime,$activeproject,$activetask,$elapsedtime);
		if($timeslippost['status'] == 201) {
			// Success; define content
			$content = "<div id='clockedout'>";
			$content .= "Clocking out of task <b>".htmlspecialchars($activetaskname)."</b> in project <b>".htmlspecialchars($activeprojectname)."</b> for contact <b>".htmlspecialchars($activecontactname)."</b>.<br />";
			$content .= "<ul><li>Clocked <b>in</b> at <b>".strftime("%a %d %b %H:%M:%S",$clockintime)."</b></li>";
			$content .= "<li>Clocked <b>out</b> at <b>".strftime("%a %d %b %H:%M:%S",$clockouttime)."</b></li>";
			$content .= "<li>Clocking in <b>".$elapsedtime." hours</b> into FreeAgentCentral</li></ul>";
			$content .= "Succesfully posted timeslip data to FreeAgentCentral, click <a href='".$timeslippost['data']."'>here</a> to verify.<br />";
			$content .= "</div>";
			// Log this clockout
			// Delete statefile
			unlink($statefile);
		} else {
	        	$content = "<div id='error'>";
			$content .= "Error submitting timeslip to FreeAgentCentral! HTTP Error code ".$timeslippost['status'].", will <b>NOT<b/> clock out!";
		        $content .= "</div>";
		}
	}

	return($content);
}

function cancel_clockin() {
	// Returns a div called cancelclockout with the 
	// Cancel this clock in
	global $statefile, $contactlist, $projectlist, $activecontact, $activeproject, $activetask, $activetaskname, $clockintime;

	// Get the contact name from our $contactlist xml by finding the $activecontact id 
	foreach ($contactlist->contact as $contact) {
		if($contact->id == $activecontact) { 
			if(isset($contact->{'organisation-name'})) {
			 	$activecontactname = (string)$contact->{'organisation-name'};
			} else {
			 	$activecontactname = (string)$contact->{'first-name'}." ".(string)$contact->{'last-name'};
			}
		}
	}

	// Get project name from our $projectlist xml by finding the $activeproject id
	foreach ($projectlist->project as $project) {
		if($project->id == $activeproject) { 
			$activeprojectname = (string)$project->name; 
		}
	}
	
	$content = "<div id='cancelclockin'>";
	$content .= "Cancelled clock-in of task <b>".htmlspecialchars($activetaskname)."</b> in project <b>".htmlspecialchars($activeprojectname)."</b> for contact <b>".htmlspecialchars($activecontactname)."</b>.<br />";
	$content .= "<ul><li>Clocked <b>in</b> at <b>".strftime("%a %d %b %H:%M:%S",$clockintime)."</b></li>";
	$content .= "<li><b>Cancelled</b> at <b>".strftime("%a %d %b %H:%M:%S",time())."</b></li></ul>";
	$content .= "</div>";
	// Log this cancel
	// Delete statefile
	unlink($statefile);
	return($content);
}

function show_recent_tasks() {
	global $contactlist, $projectlist, $timesliplist, $recenttasklimit, $activetask;

//          [0] => SimpleXMLElement Object
//              (
//                  [comment] => SimpleXMLElement Object
//                  [dated-on] => 2009-12-08T00:00:00Z
//                  [hours] => 2.0
//                  [id] => 108330
//                  [task-id] => 19800
//                  [project-id] => 19800
//                  [updated-at] => 2009-12-12T19:03:54Z
//                  [user-id] => 7220

	// Show recent tasks first
	$content = "<div id='recenttasks'>\n";

	// Dropbox for selection of last X tasks to clock into
	// We'll need to filter on user-id and on unique task-id so as to not get duplicate tasks on this list.
	// Because we only get task-id, we have to keep queries to a minimum because for each uniqke task-id we need to query
	
	$content .= "<form action='' method='POST'>\n";
	$content .= "Recent:\n\n";
	$content .= "<select name='recenttask' size='1' onchange='this.form.submit()'>\n";
	
	$recenttasklist = array();
	$count = 0;
	foreach ($timesliplist->timeslip as $timeslip) {
		$recenttask = (integer)$timeslip->{'task-id'};
		// Check to make sure we don't do this more than once per task-id
		if(!isset($recenttasklist[$recenttask]) && $count < $recenttasklimit) { 
			// Get this tasks description
			$recenttasklist[$recenttask]['id'] = $recenttask;
			$recenttasklist[$recenttask]['project'] = (integer)$timeslip->{'project-id'};
			$recenttasklist[$recenttask]['date'] = strftime((integer)$timeslip->{'dated-on'});
			$recenttasklist[$recenttask]['hours'] = (float)$timeslip->{'hours'};
			$recenttasklist[$recenttask]['updated'] = strftime((integer)$timeslip->{'updated-at'});
			// Get project name from existing XML object
			foreach ($projectlist->project as $project) {
				if($project->id == $recenttasklist[$recenttask]['project']) {
					$recenttasklist[$recenttask]['projectname'] = (string)$project->name;
					$recenttasklist[$recenttask]['contact'] = (integer)$project->{'contact-id'};
				}
			}
			// Get contact name from existing XML object
			foreach ($contactlist->contact as $contact) {
				if($contact->id == $recenttasklist[$recenttask]['contact']) {
					if(isset($contact->{'organisation-name'})) {
					 	$recenttasklist[$recenttask]['contactname'] = (string)$contact->{'organisation-name'};
					} else {
					 	$recenttasklist[$recenttask]['contactname'] = (string)$contact->{'first-name'}." ".(string)$contact->{'last-name'};
					}
				}
			}
			// Get task name.. this is way too expensive for many tasks
			$facquery = facgetdata("projects/".$recenttasklist[$recenttask]['project']."/tasks/".$recenttask);
			if($facquery["status"] == 200) {
				$recenttaskdata = simplexml_load_string($facquery["data"]);
			} else {
				print "Error fetching data for contact ".$recenttasklist[$recenttask]['contact'].", return code ".$facquery["status"]."\n";
				die();
				// TODO: cleanup
			}
			$recenttasklist[$recenttask]['name'] = (string)$recenttaskdata->{'name'};

			// And finally print our data
			$formvalue = base64_encode(serialize($recenttasklist[$recenttask]));
			$formoption = $recenttasklist[$recenttask]['contactname'].": ".$recenttasklist[$recenttask]['projectname']." - ".$recenttasklist[$recenttask]['name'];

			if($activetask == $recenttask) {
				$content .= "\t<option value='".htmlspecialchars($formvalue)."' size='12' selected='selected'>".htmlspecialchars($formoption)."</option>\n";
			} else {
				$content .= "\t<option value='".htmlspecialchars($formvalue)."' size='12'>".htmlspecialchars($formoption)."</option>\n";
			}
			
			$count = $count + 1;
		}
	}
	$content .= "</select>\n";
	$content .= "<input type='hidden' name='pageaction' value='RecentTask'/>\n";
	$content .= "<input type='hidden' name='pagestate' value='Clockedout'/>\n";
	$content .= "<input type='submit' value='Select this task'/><br />\n";
	$content .= "</form>\n";
	$content .= "</div>\n";

	return($content);
}


function show_contacts() {
	global $contactlist, $activecontact;
	// Returns a div with the contactlist
	
	$content = "<div id='contactlist'>\n";
	
	// Dropbox for selection of contact to clock in for
	
	$content .= "<form action='' method='POST'>\n";
	$content .= "Contact:\n";
	$content .= "<select name='activecontact' size='1' onchange='this.form.submit()'>\n";
	
	foreach ($contactlist->contact as $contact) {
		if(isset($contact->{'organisation-name'})) {
		 	$contact_name = (string)$contact->{'organisation-name'};
		} else {
		 	$contact_name = (string)$contact->{'first-name'}." ".(string)$contact->{'last-name'};
		}
		if(isset($activecontact) && ($activecontact == $contact->id)) {
			$content .= "\t<option value='$contact->id' selected='selected'>".htmlspecialchars($contact_name)."</option>\n";
		} else {
			$content .= "\t<option value='$contact->id'>".htmlspecialchars($contact_name)."</option>\n";
		}
	}
	$content .= "</select>\n";
	$content .= "<input type='hidden' name='pageaction' value='Projects'/>\n";
	$content .= "<input type='hidden' name='pagestate' value='Clockedout'/>\n";
	$content .= "<br/>\n";
	$content .= "<input type='submit' value='Show projects'/>\n";
	$content .= "</form>\n";
        // Add new project code
	$content .= "<form action='' method='POST'>\n";
	$content .= "<input type='submit' value='New project:'/>";
	$content .= "<input type='text' name='newprojectname' value='' size='10'/><br />\n";
	$content .= "<input type='hidden' name='activecontact' value='".$activecontact."'/>\n";
	$content .= "<input type='hidden' name='pagestate' value='Clockedout'/>\n";
	$content .= "<input type='hidden' name='pageaction' value='NewProject'/>\n";
	$content .= "</form>\n";
	$content .= "</div>\n";

	return($content);
}	

function show_projects() {	
	// Dropbox for projects to show tasks for
	global $projectlist, $activeproject, $activecontact;
	
	$content = "<div id='projectlist'>\n";
	$content .= "<form action='' method='POST'>\n";
	$content .= "Project:\n";
	$content .= "<select name='activeproject' size='1' onchange='this.form.submit()'>\n";
	foreach ($projectlist->project as $project) {
		if($project->{'contact-id'} == $activecontact) {
			if(isset($activeproject) && ($activeproject == (integer)$project->id)) {
				$content .= "\t<option value='$project->id' selected='selected'>".htmlspecialchars($project->name)."</option>\n";
			} else {
				$content .= "\t<option value='$project->id'>".htmlspecialchars($project->name)."</option>\n";
			}
		}
	}
	$content .= "</select>\n";
	$content .= "<input type='hidden' name='activecontact' value='".$activecontact."'/>\n";
	$content .= "<input type='hidden' name='pagestate' value='Clockedout'/>\n";
	$content .= "<input type='hidden' name='pageaction' value='Tasks'/>\n";
	$content .= "<br/>";
	$content .= "<input type='submit' value='Show tasks'/></form>";
        // Add new task code
	$content .= "<form action='' method='POST'>\n";
	$content .= "<input type='submit' value='New task:'/>";
	$content .= "<input type='text' name='newtaskname' value='' size='10'/><br />\n";
	$content .= "<input type='hidden' name='activecontact' value='".$activecontact."'/>\n";
	$content .= "<input type='hidden' name='activeproject' value='".$activeproject."'/>\n";
	$content .= "<input type='hidden' name='pagestate' value='Clockedout'/>\n";
	$content .= "<input type='hidden' name='pageaction' value='NewTask'/>\n";
	$content .= "</form>\n";
	$content .= "</div>\n";

	return($content);
}

function show_tasks_clockin() {
	global $activeproject, $activecontact, $activetask;
	
	// Dropbox for projects to show tasks for
	// Lets get a list of tasks for this project
	$facquery = facgetdata("projects/".$activeproject."/tasks");
	if($facquery["status"] == 200) {
		$tasklist = simplexml_load_string($facquery["data"]);
	} else {
		print "Error fetching tasks data, active project $activeproject, return code ".$facquery["status"]."\n";
			print_r($_POST);
		die();
	}
	
	$content = "<div id='tasklist'>\n";
	$content .= "<form action='' method='POST'>\n";
	$content .= "Task:\n";
	$content .= "<select name='activetask' size='1'>\n";
	foreach ($tasklist->task as $task) {
		if($activetask == (integer)$task->id) {
			$content .= "\t<option value='".(integer)$task->id."' selected='selected'>".htmlspecialchars((string)$task->name)."</option>\n";
		} else {
			$content .= "\t<option value='".(integer)$task->id."'>".htmlspecialchars((string)$task->name)."</option>\n";
		}
	}
	$content .= "</select><br />\n";
	$content .= "Clock in <input type='submit' name='pageaction' value='At'/>\n";
	$content .= "<input type='text' name='clockintime' value='".strftime("%H:%M")."' size='5'/>\n";
	$content .= "or <input type='submit' name='pageaction' value='Now'/>\n";
	$content .= "<input type='hidden' name='activecontact' value='".$activecontact."'/>\n";
	$content .= "<input type='hidden' name='activeproject' value='".$activeproject."'/>\n";
	$content .= "<input type='hidden' name='pagestate' value='Clockedout'/>\n";
	$content .= "</form>\n";
	$content .= "</div>\n";
	
	return($content);
}
	
function browserTest() {
	$xhtml = false;
	if (preg_match('/application\/xhtml\+xml(;q=(\d+\.\d+))?/i', $_SERVER['HTTP_ACCEPT'], $matches)) {
		$xhtmlQ = isset($matches[2]) ? $matches[2] : 1;
		if (preg_match('/text\/html(;q=(\d+\.\d+))?/i', $_SERVER['HTTP_ACCEPT'], $matches)) {
			$htmlQ = isset($matches[2]) ? $matches[2] : 1;
			$xhtml = ($xhtmlQ >= $htmlQ);
		} elseif(stristr($_SERVER["HTTP_USER_AGENT"],"W3C_Validator")
		|| stristr($_SERVER["HTTP_USER_AGENT"],"W3C_CSS_Validator")
		|| stristr($_SERVER["HTTP_USER_AGENT"],"WDG_Validator")) {
			$xhtml = true;
		} else {
			$xhtml = true;
		}
	}
	if ($xhtml === true) {
		header('Content-type: application/xhtml+xml');
		return "xhtml";
	} else {
		header('Content-Type: text/html; charset=utf-8');
		return "html";
	}
}


function facposttimeslip($starttime,$stoptime,$projectid,$taskid,$hours) {
	global $company, $fac_username, $fac_password, $userid, $timeslipcomment;

	$freeagentapiurl = "https://".strtolower($company).".freeagentcentral.com/timeslips";

	$headers = array(
		"Content-type: application/xml",
		"Accept: application/xml",
		"Cache-Control: no-cache",
		"Pragma: no-cache",
	);

// <timeslip>
//  <dated-on>2008-03-03T00:00:00Z</dated-on>
//   <project-id type="integer">8</project-id>
//   <task-id>38</task-id>
//   <user-id>11</user-id>
//   <hours>1</hours>
// </timeslip>
	
	// form dated-on
	$datedon = date("Y-m-d\TH:i:s",$starttime)."Z";

	$data = '<timeslip>';
	$data .= '<dated-on>'.$datedon.'</dated-on>';
	$data .= '<project-id>'.$projectid.'</project-id>';
	$data .= '<task-id>'.$taskid.'</task-id>';
	$data .= '<user-id>'.$userid.'</user-id>';
	$data .= '<hours>'.$hours.'</hours>';
	if($timeslipcomment) {
		$data .= '<comment>'.strftime("%H:%M",$starttime).'-'.strftime("%H:%M",$stoptime).'</comment>';
	} else {
		$data .= '<comment></comment>';
	}
	$data .= '</timeslip>';
	$timeslipxml = new SimpleXMLElement($data);


	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$freeagentapiurl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_HEADER, 1);
//	curl_setopt($ch, CURLOPT_USERAGENT, $defined_vars['HTTP_USER_AGENT']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_USERPWD, $fac_username.":".$fac_password);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $timeslipxml->asXML());

	$data = curl_exec($ch);

	if (curl_errno($ch)) {
		echo "Error submitting timeslip, curl error ".curl_errno($ch)."\n";
		die();
	} else {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		preg_match("/Location: (.*)/",$data,$matches);
		$location = $matches[1];
		$query = array("data" => $location, "status" => $status);
		return($query);
	}
}

function facpostnewproject($contact,$newprojectname) {
	global $company, $fac_username, $fac_password, $userid, $defaultbillingbasis, $defaultbudget, $defaultbudgetunits;

	$freeagentapiurl = "https://".strtolower($company).".freeagentcentral.com/projects";

	$headers = array(
		"Content-type: application/xml",
		"Accept: application/xml",
		"Cache-Control: no-cache",
		"Pragma: no-cache",
	);


//<project>
//  <contact-id type="integer">26</contact-id>
//  <name>Website redesign</name>
//  <billing-basis type="decimal">1</billing-basis>
//  <budget type="integer">0</budget>
//  <budget-units>Hours</budget-units>
//  <status>Active</status>
//</project>
	
	$data = '<project>';
	$data .= '<contact-id>'.$contact.'</contact-id>';
	$data .= '<name>'.$newprojectname.'</name>';
	$data .= '<billing-basis>'.$defaultbillingbasis.'</billing-basis>';
	$data .= '<budget>'.$defaultbudget.'</budget>';
	$data .= '<budget-units>'.$defaultbudgetunits.'</budget-units>';
	$data .= '<status>Active</status>';
	$data .= '</project>';
	$newprojectxml = new SimpleXMLElement($data);


	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$freeagentapiurl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_HEADER, 1);
//	curl_setopt($ch, CURLOPT_USERAGENT, $defined_vars['HTTP_USER_AGENT']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_USERPWD, $fac_username.":".$fac_password);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $newprojectxml->asXML());

	$data = curl_exec($ch);

	if (curl_errno($ch)) {
		echo "Error submitting new project, curl error ".curl_errno($ch)."\n";
		die();
	} else {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		preg_match("/Location: \/projects\/(.*)/",$data,$matches);
		$newproject = $matches[1];
		$query = array("data" => $newproject, "status" => $status);
		return($query);
	}
}


function facpostnewtask($project,$newtaskname) {
	global $company, $fac_username, $fac_password, $userid;
	
	$project = trim($project);

	$freeagentapiurl = "https://".strtolower($company).".freeagentcentral.com/projects/".$project."/tasks";

	$headers = array(
		"Content-type: application/xml",
		"Accept: application/xml",
		"Cache-Control: no-cache",
		"Pragma: no-cache",
	);


//<task>
//  <name>Website redesign</name>
//</task>
	
	$data = '<task>';
	$data .= '<name>'.$newtaskname.'</name>';
	$data .= '</task>';
	$newtaskxml = new SimpleXMLElement($data);


	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$freeagentapiurl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_HEADER, 1);
//	curl_setopt($ch, CURLOPT_USERAGENT, $defined_vars['HTTP_USER_AGENT']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_USERPWD, $fac_username.":".$fac_password);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $newtaskxml->asXML());

	$data = curl_exec($ch);

	if (curl_errno($ch)) {
		echo "Error submitting new task, curl error ".curl_errno($ch)."\n";
		die();
	} else {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		preg_match("/Location: .*\/projects\/.*\/tasks\/(.*)/",$data,$matches);
		$newtask = $matches[1];
		$query = array("data" => $newtask, "status" => $status);
		return($query);
	}
}



function facgetdata($url) {
	global $company, $fac_username, $fac_password;

	$freeagentapiurl = "https://".strtolower($company).".freeagentcentral.com/$url";

	$headers = array(
		"Content-type: application/xml",
		"Accept: application/xml",
		"Cache-Control: no-cache",
		"Pragma: no-cache",
	);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$freeagentapiurl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
//	curl_setopt($ch, CURLOPT_USERAGENT, $defined_vars['HTTP_USER_AGENT']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_USERPWD, $fac_username.":".$fac_password);

	$data = curl_exec($ch);

	if (curl_errno($ch)) {
		echo "Error querying freeagent url $freeagentapiurl";
		die();
	} else {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		$query = array("data" => $data, "status" => $status);
		return($query);
	}
}


function showheader() {
	// Returns the header of every page
	global $fullname, $lastlog;

	$content = "<div id='container'>";
	$content .= "<div id='header'>";
	$content .= "Welcome, $fullname! Time is: ".strftime("%b %d %H:%M:%S",time())."<br />";
	$content .= "Lastlog @ FreeAgentCentral: ".strftime("%b %d %H:%M:%S",$lastlog)."<br />";
	$content .= "</div>";

	return($content);
}



// Get FAC contacts data and store into $fac_contacts object
// Structure:
// SimpleXMLElement Object
// (  [@attributes] => Array
//        ( [type] => array )
//    [contact] => Array
//        (
//            [0] => SimpleXMLElement Object
//                (
//                    [id] => 12345
//                    [organisation-name] => company-x
//                    [first-name] => firstname
//                    [last-name] => lastname
//                    [email] => SimpleXMLElement Object
//                    [phone-number] => SimpleXMLElement Object
//                    [address1] => SimpleXMLElement Object
//                    [town] => SimpleXMLElement Object
//                    [region] => SimpleXMLElement Object
//                    [postcode] => SimpleXMLElement Object
//                    [address2] => SimpleXMLElement Object
//                    [address3] => SimpleXMLElement Object
//                    [contact-name-on-invoices] => true
//                    [country] => countryname
//                    [sales-tax-registration-number] => SimpleXMLElement Object
//                    [locale] => countrycode
//                    [mobile] => SimpleXMLElement Object
//                    [account-balance] => balance
//                )

// Get FAC projects data and store into $fac_projects object
// Structure:
// SimpleXMLElement Object
// ( [@attributes] => Array
//        ( [type] => array)
//    [project] => Array
//      (
//          [0] => SimpleXMLElement Object
//              (
//                  [billing-basis] => 1.0
//                  [budget] => 0
//                  [budget-units] => Hours
//                  [contact-id] => 12345
//                  [contract-po-reference] => SimpleXMLElement Object
//                      (
//                      )
//
//                  [created-at] => 2009-12-10T02:44:00Z
//                  [ends-on] => 2009-12-30T00:00:00Z
//                  [id] => 24929
//                  [is-ir35] => SimpleXMLElement Object
//                      (
//                          [@attributes] => Array
//                              (
//                                  [type] => boolean
//                                  [nil] => true
//                              )
//                      )
//                  [name] => projectname
//                  [normal-billing-rate] => 0.0
//                  [starts-on] => 2009-11-01T00:00:00Z
//                  [status] => Active
//                  [updated-at] => 2009-12-10T02:44:00Z
//                  [uses-project-invoice-sequence] => false
//              )



// Get recent timeslips to compile a "last 10 tasks" list to clock in to
// Structure:
//SimpleXMLElement Object
// ( //  [@attributes] => Array //      ( //          [type] => array //      )
//  [timeslip] => Array
//      (
//          [0] => SimpleXMLElement Object
//              (
//                  [comment] => SimpleXMLElement Object
//                  [dated-on] => 2009-12-08T00:00:00Z
//                  [hours] => 2.0
//                  [id] => 108330
//                  [task-id] => 19800
//                  [project-id] => 19800
//                  [updated-at] => 2009-12-12T19:03:54Z
//                  [user-id] => 7220
//              )


// Get user data for userid
// Structure:
// SimpleXMLElement Object
// ( [@attributes] => Array ( [type] => array) 
//  [user] => SimpleXMLElement Object
//      (
//          [company-id] => 5169
//          [email] => blah@blah.blah
//          [encrypted-ni-number] => SimpleXMLElement Object
//              ( [@attributes] => Array ( [nil] => true))
//          [first-name] => Blah
///         [id] => 7220
//          [identity-url] => SimpleXMLElement Object
//              ( [@attributes] => Array ( [nil] => true) )
//          [last-logged-in-at] => 2009-12-10T02:17:16Z
//          [last-name] => Blah
//          [old-encrypted-ni-number] => SimpleXMLElement Object
//              ( [@attributes] => Array ( [nil] => true) )
//          [opening-director-loan-account-balance] => 0.0
//          [opening-expense-balance] => 0.0
//          [opening-mileage] => 0
//          [opening-salary-balance] => 0.0
//          [opening-share-or-capital-balance] => 0.0
//          [overview-layout-left] => 1,2,3,8
//          [overview-layout-right] => 4,9,5,6,7
//          [permission-level] => 8
//          [role] => Owner
//      )
//
// )



?>
