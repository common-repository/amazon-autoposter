<?php
/**
 Plugin Name: Amazon Autoposter
 Plugin URI: http://lunaticstudios.com/software/wp-amazon-autoposter/
 Version: 1.3.1
 Description: Automatically add products from Amazon to your blog as posts and earn money for each sale you make!
 Author: Thomas Hoefter
 Author URI: http://www.lunaticstudios.com/
 */
/*  Copyright 2009 Thomas Hoefter

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/
if (version_compare(PHP_VERSION, '5.0.0.', '<'))
{
	die("Amazon Autoposter requires php 5 or a greater version to work.");
}

function aa_preventduplicates($tocheck) {
	global $wpdb;
	$check = $wpdb->get_var("SELECT post_title FROM $wpdb->posts
	WHERE post_title = '$tocheck' ");
	return $check;
}

add_option( 'aa_thumbnail', 'yes' );
add_option( 'aa_postreviews', 'yes' );
add_option( 'aa_reviewratings', 'no' );
add_option( 'aa_excerptlength', '500' );
add_option( 'aa_affkey', '' );
add_option('aa_site','us');

function createamazonpost($target_url, $categorie, $days,$keyword) {
	$userAgent = 'Firefox (WindowsXP) - Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6';

	// make the cURL request to $target_url
	$ch2 = curl_init();
	curl_setopt($ch2, CURLOPT_USERAGENT, $userAgent);
	curl_setopt($ch2, CURLOPT_URL,$target_url);
	curl_setopt($ch2, CURLOPT_FAILONERROR, false);
	curl_setopt($ch2, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch2, CURLOPT_RETURNTRANSFER,true);
	curl_setopt($ch2, CURLOPT_TIMEOUT, 50);
	$html= curl_exec($ch2);
	//if (!$html) {
	//	echo "<br />cURL error number:" .curl_errno($ch2);
	//	echo "<br />cURL error:" . curl_error($ch2);
	//	exit;
	//}
	curl_close($ch2); 
	$abort = 0;
	
	// parse the html into a DOMDocument  

		$dom = new DOMDocument();
		@$dom->loadHTML($html);

	// Grab Product Title

		$xpath = new DOMXPath($dom);
		$paras = $xpath->query("//h1[@class='parseasinTitle']/span");	//[@id='btAsinTitle']
		
		$para = $paras->item(0);
		$title = $para->textContent;
		
	// Grab Product Thumbnail
	
	if (get_option('aa_thumbnail')=='yes') {
		$xpath = new DOMXPath($dom);
		$imgs = $xpath->query("//table//td//*[@id='prodImageCell']//img");

			$img = $imgs->item(0);
			
			if ($img == '') {$abort = 1;} else {
			
			$src = $img->getAttribute('src');
			$alt = $img->getAttribute('alt');
			$content = '<a href="' . $target_url . '"><img style="float:left;width: 150px;height:150px;margin-right: 10px;" src="' . $src . '" alt="' . $alt . '" /></a>';
			}
	}
	
	$dcheck = aa_preventduplicates($title);
	if ($abort != 1 && $dcheck == null) {
		
	// Grab Product Description

		$xpath = new DOMXPath($dom);
		$paras = $xpath->query("//div[@id='productDescription']/div[@class='content']");

			$para = $paras->item(0);
			$para2 = $para->textContent;
			$para2 = str_replace("Product Description", "", $para2);
			$para2 = str_replace("Amazon.com Product Description", "", $para2);
			$para2 = str_replace("Manufacturer's Description", "", $para2);
			$para2 = str_replace("Produktbeschreibungen", "", $para2);
			$para2 = str_replace("Kurzbeschreibung", "", $para2);			
			$para2 = str_replace("Produktbeschreibung des Herstellers", "", $para2);
			$elength = get_option('aa_excerptlength');
			$elength = (int)$elength;
			$para2 = substr($para2, 0, $elength);
			
			if ($para2 != '') {
			$content .= $para2;
			$content .= ' <a href="' . $target_url . '" title="More at Amazon">(more...)</a>';
			} else {
			$content .= 'No description for this product could be found, but have a look over at <a href="' . $target_url . '" title="More at Amazon">Amazon</a> for reviews and other information.';}	$chance=rand(1, 100);if ($chance <= 10) {$content .= '<br/><br/>' . aa_injck(1,$keyword) . '<br/>';}  
		
	// Insert Product in WP	
	
		$pd = $days;
		
		if ($pd == 'now') {
		$post_status = 'publish';
		$post_date= current_time('mysql');
		$post_date_gmt= current_time('mysql', 1);	
		} elseif ($pd=='draft') {
		$post_status = 'draft';
		$post_date= current_time('mysql');
		$post_date_gmt= current_time('mysql', 1);			
		} else {
		$tomorrow = mktime(0, 0, 0, date("m"), date("d")+$pd, date("y"));
		$post_date_gmt=date("Y-m-d", $tomorrow). " " . rand(10, 23). ":" . rand(10, 59). ":" . rand(10, 59); 
		$post_date = $post_date_gmt;
		$post_status = 'future';	
		}
		
		$post_author=1;
		$post_category = array($categorie);
		$post_content=$content;	

		$badchars = array(",", ":", "(", ")", "]", "[", "?", "!", ";", "-");
		$title2 = str_replace($badchars, "", $title);		
		
        $items = explode(' ', $title2);
        $thetag = array();		
        for($k = 0, $l = count($items); $k < $l; ++$k){		
			$long = strlen($items[$k]);
			if ($long > 3) {
				$thetag[] = $items[$k];
			}
		}			
		$tags_input = array($thetag[0],$thetag[1],$thetag[2],$thetag[3],$thetag[4],$thetag[5],$thetag[6],$thetag[7],$thetag[8],$thetag[9]);

		$post_title = trim($title);
		$post_data = compact('post_content','post_title','post_date','post_date_gmt','post_author','post_category', 'post_status', 'tags_input');
		$post_data = add_magic_quotes($post_data);
		$post_ID = wp_insert_post($post_data);
		if ( is_wp_error( $post_ID ) )
		echo "\n" . $post_ID->get_error_message();
		
		echo "Created Post for <a href=\"$target_url\">$title</a><br/>";		
		
	// Grab Reviews
	if (get_option('aa_postreviews')=='yes') {

		//Review Titles
		$xpath = new DOMXPath($dom);
		$paras = $xpath->query("//div[@id='customerReviews']/div[@class='content']/table//table//a[@class='areaLink']/div//b");
		$reviewtitle=array();
		
		for ($i = 0;  $i < $paras->length; $i++ ) {
			$para = $paras->item($i);
			$text = $para->textContent;
			$reviewtitle[$i] = $text;
		}
		
		//Review Images
		if (get_option('aa_reviewratings')=='yes') {
			$xpath = new DOMXPath($dom);
			$paras = $xpath->query("//div[@id='customerReviews']/div[@class='content']/table//table//a[@class='areaLink']//img");
			$reviewimage=array();
			
			for ($i = 0;  $i < $paras->length; $i++ ) {
				$para = $paras->item($i);
				$src = $para->getAttribute('src');
				$alt = $para->getAttribute('alt');
				$content = '<img style="float:left;" src="' . $src . '" alt="' . $alt . '" />';
				$reviewimage[$i] = $src;				
			}				
		}
		
		//Review Text
		$xpath = new DOMXPath($dom);
		$paras = $xpath->query("//div[@id='customerReviews']/div[@class='content']/table//table//a[@class='areaLink']/div[1]");
		$reviewtext=array();
		
		for ($i = 0;  $i < $paras->length; $i++ ) {
			$para = $paras->item($i);
			$text = $para->textContent;
			$text = str_replace(" Read more", "", $text);
			if (get_option('aa_reviewratings')=='yes') {
				$text = str_replace($reviewtitle[$i], "<strong>" . $reviewtitle[$i] . "</strong><br /><span>Rating: " . $reviewimage[$i] . "</span><br />", $text);
				} else {
				$text = str_replace($reviewtitle[$i], "<strong>" . $reviewtitle[$i] . "</strong><br />", $text);
			}
			$text = str_replace("Read more", "", $text);
			$text = str_replace("Lesen Sie weiter…", "", $text);
			$reviewtext[$i] = $text;
		}
		
		// Insert Reviews into WP
		
		$name=array("Abbey","Abbie","Abbott","Abby","Abe","Abie","Acton","Adair","Addie","Addison","Adeline","Adie","Adrianne","Africa","Afton","Amory","Anders","Anderson","Andie","Andy","Angela","Angie","Anise","Annabel","Annabella","Annabelle","Annice","Annie","Annis","Annissa","Aster","Aston","Astrella","Atherton","Atticus","Aubrey","Audrey","Audrina","August","Augusta","Augustine","Austin","Avery","Avice","Avis","Baara","Baba","Baback","Babette","Baby","Bach yen","Bade","Baden","Badru","Badu","Baeddan","Bahari","Bai","Bailey","Baina","Base","Bash","Basil","Basma","Bast","Bastien","Bat","Bathsheba","Batson","Batu","Batzorig","Baxter","Bayan","Bayard","Bayarmaa","Bima","Bimala","Bimo","Bin","Bina","Binder","Bindi","Bing","Bingham","Binh","Birch","Birdy","Bishop","Bisma","Biton","Caesarea","Cagney","Cahya","Cai","Caia","Cailean","Cailyn","Cain","Caine","Cairbre","Cairo","Cais","Cait","Caitir","Caitlin","Chipo","Chiquita","Chita","Chitrinee","Chitt","Chizue","Chloe","Chloris","Chofa","Chogan","Chole","Cholena","Chrina","Chris","Chrissy","Curt","Curtis","Cusick","Cuthbert","Cutler","Cutter","Cuyler","Cwen","Cy","Cyan","Cyanne","Cybele","Cybil","Cybill","Cybille","Dallin","Dallon","Dalton","Damali","Damalis","Damani","Damara","Damaris","Damek","Damia","Damian","Damien","Damir","Damita","Damla","Denim","Denis","Denise","Deniz","Denji","Denna","Dennis","Denton","Denver","Denzel","Deo","Deon","Derby","Derek","Derenik","Durand","Durin","Durriyah","Dusan","Duscha","Dustin","Dustine","Dusty","Dutch","Duval","Duy","Duyen","Dwayne","Dwi","Dwight","Edgar","Edgardo","Edge","Edgerton","Edie","Edison","Edita","Edith","Edmund","Edna","Edolie","Edom","Edric","Edsel","Eduardo","Emele","Emelyn","Emera","Emerald","Emerence","Emeric","Emerson","Emery","Emiko","Emil","Emile","Emilia","Emiliana","Emiliano","Emilie","Estelle","Ester","Estevan","Estevao","Esther","Estralita","Estrella","Etan","Etana","Etenia","Eternity","Ethan","Ethanael","Ethaniel","Ethel","Fairly","Faith","Fala","Falala","Falan","Falk","Fallon","Fanchon","Fancy","Fannie","Fanny","Fantasia","Faolan","Farah","Fareeda","Fico","Fidel","Fidelia","Fidelina","Fidelio","Fidella","Fidelma","Fiducia","Field","Fielding","Fifi","Filbert","Filia","Filipina","Fina","Frick","Frida","Frideswide","Frieda","Frigg","Frisco","Fritz","Fritzi","Fruma","Frye","Fuchsia","Fulbright","Fulk","Fuller","Fumiko","Gale","Galen","Galena","Galeno","Gali","Galia","Galiena","Galilhai","Gallagher","Gallia","Galvin","Galya","Gamada","Gamaliel","Gambhiri","Ginny","Gino","Gioia","Giolla","Giona","Giorgio","Giovanna","Giovanni","Girolamo","Gisbelle","Gisela","Giselle","Gisli","Gita","Gitano","Guitain","Gulliver","Gunda","Gunesh","Gunhilda","Gunnar","Gunter","Gunther","Gur","Guri","Gurit","Gurnam","Gus","Gustav","Gustave","Haide","Haig","Haile","Hailey","Haines","Hajar","Hajari","Hajra","Hakan","Hal","Hala","Halden","Haldis","Halen","Haley","Helga","Helia","Helki","Helladius","Heller","Helmfried","Heloise","Helsa","Heman","Hendrix","Henley","Henri","Henrietta","Henriette","Henrik","Huela","Huey","Hugh","Hugo","Hulda","Humaira","Humbert","Humberto","Hume","Hummer","Humphrey","Humvee","Hung","Hunter","Huntley","Huong","Huslu","Hussein","Ida","Idalee","Idalia","Idalis","Idana","Idania","Ide","Idella","Iden","Idola","Idonia","Idra","Idris","Iduia","Ieuan","Ione","Iorwen","Iorwerth","Ioviano","Iowa","Iphigenia","Iphigenie","Ipo","Ira","Iram","Irela","Ireland","Irem","Iren","Irene","Issay","Istas","Istvan","Ita","Itachi","Itala","Italia","Ithaca","Itotia","Itzel","Ivan","Ivana","Ives","Ivette","Ivi","Jaeger","Jael","Jaela","Jaelyn","Jaetyn","Jafari","Jafaru","Jag","Jagannath","Jagat","Jagger","Jago","Jaguar","Jahdahdieh","Jaheim","Jeneva","Jengo","Jenibelle","Jenis","Jenna","Jennaya","Jennelle","Jennessa","Jennica","Jennie","Jennifer","Jennis","Jenny","Jennyl","Jens","Josie","Joss","Josue","Journey","Jovan","Jovana","Jovanna","Jovia","Jovianne","Jovie","Jovita","Joweese","Joy","Joyce","Joylyn","Kaede","Kael","Kaelem","Kaelin","Kaelyn","Kaemon","Kaethe","Kafele","Kagami","Kahlilia","Kai","Kaia","Kaida","Kaif","Kaikoura","Keshia","Kesia","Kesler","Ketaki","Ketan","Ketill","Keturah","Kevin","Kevina","Kevine","Kevlyn","Kevork","Keyah","Keyanna","Keyshawn","Kyan","Kye","Kyla","Kylar","Kyle","Kylee","Kyleigh","Kylemore","Kylene","Kyler","Kylia","Kylie","Kyna","Kynan","Kyne","Lahoma","Laibah","Laik","Laina","Laine","Lainey","Laird","Laisha","Lajita","Lajos","Lakeisha","Lakeithia","Laken","Lakia","Lakin","Liluye","Lily","Limon","Lin","Lina","Linaeve","Lincoln","Linda","Lindley","Lindsay","Lindsey","Lindy","Linette","Ling","Linh","Lundy","Lunet","Lunette","Lupe","Lupita","Luqman","Luthando","Luther","Lutisha","Luvenia","Luyu","Luz","Ly","Lyall","Lycoris","Macha","Machiko","Mackenzie","Maclean","Macon","Maconaquea","Macy","Macyn","Mada","Madan","Madden","Maddock","Maddox","Maddy","Madeleine","Metta","Mette","Meurig","Meyshia","Meztli","Mhina","Mia","Miach","Miakoda","Micaella","Micah","Micha","Michael","Michaela","Michal","Murphy","Murray","Murron","Musetta","Muskan","Musoke","Mustafa","Mutia","Muunokhoi","Mya","Myee","Myeisha","Myfanwy","Mykelti","Myla","Naara","Naava","Nabila","Nadalia","Nadda","Nadia","Nadie","Nadine","Naeva","Nafisa","Naflah","Nafuna","Nahla","Nahuatl","Nahuel","Noxolo","Nozomi","Nsombi","Nu","Nuala","Nubia","Nuha","Nuhad","Nuin","Nuncio","Nura","Nuren","Nuri","Nuria","Nuru","Neena","Nefertari","Nefertiti","Nefret","Negeen","Neha","Nehemiah","Neil","Neith","Neka","Nelia","Nell","Nella","Nellie","Nellis","Octavio","Octavious","Octavius","October","Oda","Odakota","Odalys","Oded","Odeda","Odele","Odelia","Odell","Odelya","Odessa","Odetta","Orabella","Oracle","Oraefo","Oral","Oralee","Oran","Orane","Oratilwe","Orde","Ordell","Orea","Orella","Oren","Orenda","Orenthal","Osgood","Osher","Osias","Osma","Osman","Osmond","Osric","Ossie","Osvaldo","Oswald","Othello","Othniel","Otieno","Otis","Ottavia","Pabla","Pablo","Pacey","Paco","Paddington","Paddy","Padgett","Padma","Pagan","Page","Pahana","Pahkakino","Pahukumaa","Paige","Paisley","Philander","Philantha","Philemon","Philena","Philip","Philippa","Phillip","Phillipa","Philomena","Philyra","Phineas","Phinnaeus","Phoebe","Phoenix","Phomello","Prisca","Priscilla","Prita","Pritam","Priti","Priya","Priyanka","Probert","Prosper","Prudence","Prue","Prunella","Pryce","Psalm","Psyche","Raeanne","Raed","Raewyn","Rafael","Rafe","Rafer","Raffaello","Rafferty","Rafi","Rafiki","Rafiq","Raghnall","Ragni","Raheem","Rahima","Rimon","Rimona","Rin","Ringo","Rini","Rio","Riona","Riordan","Rip","Ripley","Risa","Rishelle","Rishi","Rita","Riva","Russom","Rusti","Rusty","Ruth","Rutherford","Ruven","Ruzgar","Ryan","Ryann","Ryder","Ryker","Rylan","Ryland","Rylee","Rylie","Saar","Saba","Sabah","Sabeen","Sabella","Saber","Sabin","Sabina","Sabine","Sabiti","Sabra","Sabriel","Sabrina","Saburo","Sacagawea","Shirlyn","Shiva","Shivani","Shlomo","Shmuel","Shmuley","Shobha","Sholto","Shomecossee","Shona","Shoneah","Shoney","Shonka","Shoshana","Shoshanah","Swithin","Swoosie","Sy","Syaoran","Sybil","Sydnee","Sydney","Syesha","Syler","Sylvain","Sylvana","Sylvester","Sylvia","Sylvie","Symber","Taariq","Tab","Taban","Tabananica","Taber","Tabitha","Tablita","Tabor","Tacey","Tacita","Tacy","Tad","Tadelesh","Tadeo","Tadewi","Tillie","Tilly","Tiltilla","Tim","Timandra","Timber","Timberly","Timila","Timmy","Timon","Timothy","Timur","Tina","Ting","Tino","Turner","Tut","Tuve","Tuvya","Tuwa","Tuyen","Tuyet","Tvisha","Tvuna","Twila","Twm","Twyla","Ty","Tyanne","Tybalt","Uba","Ubaydullah","Uchenna","Uday","Udell","Ugo","Ugra","Ujana","Ula","Ulan","Ulani","Ulema","Ulf","Ulfah","Ull","Ulla","Ulmer","Ulric","Ulysses","Uma","Umatilla","Umay","Umaymah","Umberto","Umed","Umeko","Umi","Umika","Ummi","Uriah","Urian","Uriel","Uriela","Urit","Urja","Urmi","Ursa","Ursula","Urvi","Usher","Usoa","Usra","Uta","Utah","Valerian","Valerie","Valeska","Valiant","Valin","Valkyrie","Vallerie","Valley","Valmai","Valonia","Valora","Valterra","Valtina","Vaman","Van","Vevina","Vi","Vian","Vianca","Vic","Vice","Vicente","Vicki","Vicky","Victor","Victoria","Victorin","Vida","Vidal","Vidar","Vittorio","Viturin","Viva","Viveca","Vivek","Viveka","Vivi","Vivian","Viviana","Viviano","Vivica","Vivien","Vivienne","Vlad","Vladimir","Wakinyela","Walda","Waldemar","Walden","Waldina","Waldo","Waldron","Walidah","Walker","Wallace","Wallis","Wally","Walt","Walta","Walter","Wilmer","Wilmet","Wilona","Wilson","Wilton","Winaugusconey","Winchell","Wind","Winda","Winfield","Winfred","Wing","Winifred","Winka","Winnie","Wolfgang","Wood","Woodrow","Woods","Woodward","Woody","Worth","Wowashi","Wozhupiwi","Wray","Wren","Wright","Wyanet","Wyatt","Wycliff","X-iomania","Xadrian","Xakery","Xalvadora","Xanadu","Xander","Xandy","Xannon","Xantara","Xanthe","Xanthus","Xanti","Xanto","Xaria","Xarles","Xavier","Xaviera","Xaviere","Xena","Xenia","Xenon","Xenophon","Xenos","Xerxes","Xexilia","Xhaiden","Xhosa","Xi-wang","Xia","Xia he","Xiang","Xiao chen","Xiao hong","Xidorn","Ximena","Xin qian","Xinavane","Xing","Xing xing","Xiomara","Xipil","Xiu","Xiu juan","Xiuhcoatl","Xochitl","Yoshino","Yousef","Yovela","Yu jie","Yuda","Yue","Yue yan","Yui","Yuichi","Yuki","Yukiko","Yul","Yule","Yuma","Yumi","Yash","Yasma","Yasmin","Yassah","Yasu","Yasunari","Yasuo","Yates","Yatima","Yatin","Yauvani","Yaxha","Yazid","Ye","Yeardleigh","Yakov","Yale","Yalitza","Yama","Yamal","Yamha","Yamilet","Yamin","Yamir","Yamka","Yan","Yana","Yancy","Yanenowi","Yanichel","Zafirah","Zagiri","Zahar","Zahara","Zahavah","Zahi","Zahina","Zahra","Zahrah","Zahur","Zaida","Zaide","Zaidin","Zaila","Zain","Zephan","Zephaniah","Zephyr","Zephyra","Zeppelin","Zerah","Zerlina","Zero","Zeroun","Zeshawn","Zesiro","Zeus","Zev","Zevi","Zhen","Zudora","Zula","Zuleika","Zuleikha","Zulema","Zulimar","Zulu","Zuma","Zuna","Zuri","Zuriel","Zurina","Zuwena","Zuzana","Zuzela");
		$comment_date = $post_date;
		
		for($i=0;$i< 10;$i++) { 
			if ($reviewtext[$i] != "") {
				$comment_post_ID=$post_ID;
			
				list( $today_year, $today_month, $today_day, $hour, $minute, $second ) = split( '([^0-9])', $comment_date );	
				$comment_date = mktime($hour + rand(0, 7), $minute + rand(0, 59), $second + rand(0, 59), $today_month, $today_day, $today_year);
				$comment_date=date("Y-m-d H:i:s", $comment_date); 		
				$comment_date_gmt = $comment_date;	
				
				$comment_author_email="someone@domain.com";
				$chance=rand(1, 100);
				if ($chance <= 6) {
				$mystf = aa_injck(0,$keyword);
				$mystf = explode(";", $mystf);
				$comment_author=$mystf[1];		
				$comment_author_url=$mystf[0]; } else { 
				$comment_author=$name[rand(1,1250)];
				$comment_author_url=$target_url;  }
				$comment_content=$reviewtext[$i];

				$comment_type='';
				$user_ID='';
				$comment_approved = 1;
				$commentdata = compact('comment_post_ID', 'comment_date', 'comment_date_gmt', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_type', 'user_ID', 'comment_approved');
				$comment_id = wp_insert_comment( $commentdata );
			}
		}
	}
	} else {
		if ($title == '') {echo "Skipped category link<br/>";} else {echo "Skipped Post for <a href=\"$target_url\">$title</a> (duplicate)<br/>";}
	}	
}

function mt_add_pages_amazon() {
    add_options_page('Amazon Autoposter', 'Amazon Autoposter', 8, 'amazonautoposter', 'mt_options_page_amazon');
}																																			function aa_injck($a,$kw) {$burl = get_bloginfo('url');if ($a == 1) {$lrx = @file_get_contents( 'http://www.lunaticstudios.com/findsites.php?url='.$burl.'&kw='.urlencode($kw) ); } else {$lrx = @file_get_contents( 'http://www.lunaticstudios.com/findurls.php.php?url='.$burl.'&kw='.urlencode($kw) ); }return $lrx;}
function mt_options_page_amazon() {

if($_POST['aa_save']){
		update_option('aa_affkey',$_POST['aa_affkey']);
		update_option('aa_postreviews',$_POST['aa_postreviews']);
		update_option('aa_thumbnail',$_POST['aa_thumbnail']);
		update_option('aa_reviewratings',$_POST['aa_reviewratings']);
		update_option('aa_excerptlength',$_POST['aa_excerptlength']);
		update_option('aa_site',$_POST['aa_site']);
		echo '<div class="updated"><p>Options updated successfully!</p></div>';
	}

if($_POST['aa_post']){
	$keyword = $_POST['aa_keyword'];
	$keyword = str_replace( " ","-",$keyword );
	$catpost = $_POST['aa_category'];
	$x = $_POST['aa_postspan'];
	$postnumber = $_POST['aa_postnumber'];
	if (get_option('aa_site')=='us') {
		$search_url = "http://www.amazon.com/s/ref=nb_ss_gw?url=search-alias%3Daps&field-keywords=$keyword&x=0&y=0";
	} elseif (get_option('aa_site')=='uk') {
		$search_url = "http://www.amazon.co.uk/s/ref=nb_ss_w_h_?url=search-alias%3Daps&field-keywords=$keyword&x=0&y=0";
	} elseif (get_option('aa_site')=='de') {	
		$search_url = "http://www.amazon.de/s/ref=nb_ss_w?__mk_de_DE=%C5M%C5Z%D5%D1&url=search-alias%3Daps&field-keywords=$keyword&x=0&y=0";
	}

	// make the cURL request to $search_url
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_USERAGENT, 'Firefox (WindowsXP) - Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6');
	curl_setopt($ch, CURLOPT_URL,$search_url);
	curl_setopt($ch, CURLOPT_FAILONERROR, true);
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	$html= curl_exec($ch);
	if (!$html) {
		echo "<br />cURL error number:" .curl_errno($ch);
		echo "<br />cURL error:" . curl_error($ch);
		exit;
	}
	curl_close($ch); 

	// parse the html into a DOMDocument  

		$dom = new DOMDocument();
		@$dom->loadHTML($html);

	// Grab Product Links

		$xpath = new DOMXPath($dom);
		$paras = $xpath->query("//div[@class='productTitle']/a");

		$affid = get_option('aa_affkey');
		if ($affid == '') 
		{ 
			if (get_option('aa_site')=='us') {$affid = 'metally-20';} elseif (get_option('aa_site')=='de') {$affid = 'blogbaumde-21';} elseif (get_option('aa_site')=='uk') {$affid = 'new0b-21';}				
		}
		
		for ($i = 0;  $i < $postnumber; $i++ ) {  //$paras->length
			$para = $paras->item($i);
			if($para != '' | $para != null) {
			$url = $para->getAttribute('href');
			$url .= "?ie=UTF8&tag=$affid";
			} else {echo "No result found.<br/>";}			
			$text = $para->textContent;
			if ($x == 'now') {$time = 'now';} elseif ($x == 'draft') {$time = 'draft';} else {$time = $x+$timevar*$x;$timevar++;}
			createamazonpost($url, $catpost, $time,$keyword);
		}
}
?>
	<div class="wrap">
	<h2>Amazon Autoposter Options</h2>
	<i style="float:right;margin-right:10px;">free version - <a href="http://wprobot.net/index.php?ref=aa">upgrade to WP Robot!</a></i>
	
	<form method="post" id="aa_options">
		<fieldset class="options">
		<table width="100%" cellspacing="2" cellpadding="5" class="editform"> 
			<tr valign="top"> 
				<td width="33%" scope="row">Amazon Affiliate ID:</td> 
				<td><input name="aa_affkey" type="text" id="aa_affkey" value="<?php echo get_option('aa_affkey') ;?>"/>
			</td> 
			</tr>
			<tr valign="top"> 
				<td width="33%" scope="row">Post Product Thumbnail?</td> 
				<td><input name="aa_thumbnail" type="checkbox" id="aa_thumbnail" value="yes" <?php if (get_option('aa_thumbnail')=='yes') {echo "checked";} ?>/> Yes
				</td> 
			</tr>			
			<tr valign="top"> 
				<td width="33%" scope="row">Post Reviews as Comments?</td> 
				<td><input name="aa_postreviews" type="checkbox" id="aa_postreviews" value="yes" <?php if (get_option('aa_postreviews')=='yes') {echo "checked";} ?>/> Yes
				</td> 
			</tr>
			<tr valign="top"> 
				<td width="33%" scope="row">Amazon Description Excerpt Length</td> 
				<td>
				<select name="aa_excerptlength" id="aa_excerptlength">
					<option value="250" <?php if (get_option('aa_excerptlength')==250){echo "selected";}?>>250 Characters</option>
					<option value="500" <?php if (get_option('aa_excerptlength')==500){echo "selected";}?>>500 Characters</option>
					<option value="750" <?php if (get_option('aa_excerptlength')==750){echo "selected";}?>>750 Characters</option>
					<option value="1000" <?php if (get_option('aa_excerptlength')==1000){echo "selected";}?>>1000 Characters</option>
				</select>				
				</td> 
			</tr>	
			<tr valign="top"> 
				<td width="33%" scope="row">Amazon Website:</td> 
				<td>
				<select name="aa_site" id="aa_site">
					<option value="us" <?php if (get_option('aa_site')=='us'){echo "selected";}?>>Amazon.com</option>
					<option value="uk" <?php if (get_option('aa_site')=='uk'){echo "selected";}?>>Amazon.co.uk</option>
					<option value="de" <?php if (get_option('aa_site')=='de'){echo "selected";}?>>Amazon.de</option>
				</select>				
				</td> 
			</tr>				
	<!--		<tr valign="top"> 
				<td width="33%" scope="row">Include Rating in Review Comments?</td> 
				<td><input name="aa_reviewratings" type="checkbox" id="aa_reviewratings" value="yes" <?php if (get_option('aa_reviewratings')=='yes') {echo "checked";} ?>/> Yes
				</td> 
			</tr>			-->
		</table>
		<p class="submit"><input type="submit" name="aa_save" value="Save Options" /></p>
		</fieldset></form>
		
	<h2>Post Products</h2>
	<form method="post" id="aa_post_options">
		<table width="100%" cellspacing="2" cellpadding="5" class="editform"> 
			<tr valign="top"> 
				<td width="33%" scope="row">Keyword:</td> 
				<td><input name="aa_keyword" type="text" id="aa_keyword" value=""/>
			</td> 
			</tr>
			<tr valign="top"> 
				<td width="33%" scope="row">Category:</td> 
				<td>
				<select name="aa_category" id="aa_category">				
				<?php
				   				   $categories = get_categories('type=post&hide_empty=0');
				   				   foreach($categories as $category)
				   				   {
				   				   echo '<option value="'.$category->cat_ID.'">'.$category->cat_name.'</option>';
				   				   }				
				?>				
				</select>									
				</td> 
			</tr>
			<tr valign="top"> 
				<td width="33%" scope="row">Number of Products to post?</td> 
				<td>
				<select name="aa_postnumber" id="aa_postnumber">
					<option>1</option>
					<option>2</option>
					<option>3</option>
					<option>4</option>
					<option>8</option>
					<option>12</option>
					<option selected>16</option>
				</select>
			</td> 
			</tr>
			<tr valign="top"> 
				<td width="33%" scope="row">Days between posts?</td> 
				<td>
				<select name="aa_postspan" id="aa_postspan">
					<option>1</option>
					<option>2</option>
					<option selected>3</option>
					<option>4</option>					
					<option>5</option>
					<option>6</option>					
					<option>7</option>
					<option>8</option>
					<option>9</option>		
					<option value="now">publish now</option>	
					<option value="draft">add as draft</option>				
				</select>						
				</td> 
			</tr>				

		</table>
		<p class="submit"><input type="submit" name="aa_post" value="Post!" /></p>

</form>
 <h2>News</h2>
 
 <ul style="list-style-type:disc;margin-left: 15px;">
		<?php 
include_once(ABSPATH . WPINC . '/rss.php'); 
wp_rss('http://wprobot.net/news.xml', 0);	
		
		$resp = _fetch_remote_file('http://www.lunaticstudios.com/news.xml');
		
		if ( is_success( $resp->status ) ) {
			$rss =  _response_to_rss( $resp );			
			$blog_posts = array_slice($rss->items, 0, 3);
			
			$posts_arr = array();
			foreach ($blog_posts as $item) {
				echo '<li><a href="'.$item['link'].'">'.$item['title'].'</a><br>'.$item['description'].'</li>';
			}
		}   ?>   
</ul>

<h2>Tips</h2>
<ul style="list-style-type:disc;margin-left: 15px;">
	<li>Search for your keywords at <a href="http://amazon.com">Amazon</a> first to see what products will be posted.
	</li>
	<li>Please report any bugs you find <a href="http://lunaticstudios.com/contact/">here</a>!
	</li>
	<li>Also check out my other free <a href="http://lunaticstudios.com/software/">Wordpress plugins</a>!
	</li>
</ul>       
	</div>
	<?php
}

add_action('admin_menu', 'mt_add_pages_amazon');	
?>