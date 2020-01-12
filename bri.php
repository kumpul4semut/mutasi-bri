<?php 
include "simple_html_dom.php";

class bri
{
	private $csrf;

	private $username;

	private $password;

	private $rekening;

	private $tgl;
	
	function __construct()
	{
		if ($_GET['username']) {
			$this->username = $_GET['username'];
			$this->password = $_GET['password'];
			$this->rekening = $_GET['rekening'];
			$this->tgl = date('Y-m-d', time());
		}
		unlink('cookie.txt');
		unlink('hasil.txt');
		unlink('cap.png');
		unlink('newcap.png');
		unlink('afterlogin.html');
		unlink('mutasi.html');
		unlink('hasil_mutasi.json');
	}
	public function rq($url){
		$cookie = 'cookie.txt';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
		return curl_exec($ch);
	}

	public function prepare(){
		$url = 'https://ib.bri.co.id/ib-bri/Login.html';
		$rq = $this->rq($url);

		$html = new simple_html_dom();

		$html->load($rq);

		foreach($html->find('form') as $i){
			$this->csrf = $i->first_child()->first_child()->value;
		}

		return $this->csrf;
	}

	public function cookie(){
		//get data cookie from text
		$a = file_get_contents("cookie.txt");
		$b = explode('/', $a);

		//get session
		$session = $b[5];
		$splitS = explode('	', $session);
		//name seassion
		$NS = $splitS[3];
		//value seassion
		$nS = $splitS[4];
		$vS = explode('
		', $nS);
		$VS = substr($vS[0], 0, -13);
		// =======>this result session
		$sess = $NS.'='.$VS.';';


		//get cookie
		$cookie = $b[6];
		$splitC = explode('	', $cookie);
		//name cookie
		$NC = $splitC[3];
		//value cookie
		$VC = $splitC[4];
		//========this result cookie
		$cook = $NC.'='.$VC;

		//merge cookie all
		$cookies = $sess.' '.$cook;
		return $cookies;
	}

	public function getCap(){
		// header('Content-Type: image/png');
        unlink('cap.png');
        $ch = curl_init();

        $fp = fopen('cap.png', 'wb');
        // $cookie = file_get_contents("cookie.txt");
        define("COOKIE_FILE", "cookie.txt");
        curl_setopt($ch, CURLOPT_URL, 'https://ib.bri.co.id/ib-bri/login/captcha' );
		curl_setopt($ch, CURLOPT_COOKIEJAR, COOKIE_FILE);
		curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIE_FILE);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        // convert img to file txt
        shell_exec("convert cap.png -flatten -fuzz 20% -trim +repage -white-threshold 5000 -type bilevel newcap.png");
        shell_exec('tesseract newcap.png hasil');

        return file_get_contents("hasil.txt");
	}

	public function login($csrf, $cap){
		$arrPostData = [
        	'csrf_token_newib' => $csrf,
        	'j_password' => $this->password,
        	'j_username' => $this->username,
        	'j_plain_username' => '',
        	'preventAutoPass' => '',
        	'j_plain_password' => '',
        	'j_code' => $cap,
        	'j_language' => 'in_ID'     
    	];

    	$postdata = http_build_query($arrPostData);
    	$ch = curl_init();
    	// $cookie = file_get_contents("cookie.txt");
    	define("COOKIE_FILE", "cookie.txt");
    	curl_setopt($ch, CURLOPT_URL, 'https://ib.bri.co.id/ib-bri/Homepage.html');
    	curl_setopt($ch, CURLOPT_COOKIEJAR, COOKIE_FILE);
		curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIE_FILE);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.75 Safari/537.36");
    	curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    	curl_setopt($ch, CURLOPT_POST, 1);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
    	$result = curl_exec($ch);
        // return $result;
        usleep(100);
        file_put_contents('afterlogin.html', $result);
	}

	public function prepareMutasi(){
		$ch = curl_init();
		define("COOKIE_FILE", "cookie.txt");
		curl_setopt($ch, CURLOPT_URL,'https://ib.bri.co.id/ib-bri/AccountStatement.html');
		curl_setopt($ch, CURLOPT_COOKIEJAR, COOKIE_FILE);
		curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIE_FILE);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.75 Safari/537.36");
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		$html = new simple_html_dom();

		$html->load($result);
		$newtoken = '';

		foreach($html->find('form') as $i){
			$newtoken = $i->first_child()->first_child()->value;
		}

		if (empty($newtoken)) {
			$error = [
            		'status' => 'false',
            		'message' => 'login failed'
            	];

			return json_encode($error);
		}else{
			$form = $html->find('form',0);
			$arrPostData = [
	        	'csrf_token_newib' => $newtoken,
	        	'FROM_DATE' => $this->tgl,
	        	'TO_DATE' => date('Y-m-d'),
	        	'download' => '',
	        	'data-lenght' => '5',
	        	'ACCOUNT_NO' => $this->rekening,
	        	'VIEW_TYPE' => 2,
	        	'DDAY1' => DateTime::createFromFormat('Y-m-d',date('Y-m-d'))->format('d'),
	        	'DMON1' => DateTime::createFromFormat('Y-m-d',date('Y-m-d'))->format('m'),
	        	'DYEAR1' => DateTime::createFromFormat('Y-m-d',date('Y-m-d'))->format('Y'),
	        	'DDAY2' => DateTime::createFromFormat('Y-m-d',date('Y-m-d'))->format('d'),     
	        	'DMON2' => DateTime::createFromFormat('Y-m-d',date('Y-m-d'))->format('m'),
	        	'DYEAR2' => DateTime::createFromFormat('Y-m-d',date('Y-m-d'))->format('Y'),
	        	'MONTH' => DateTime::createFromFormat('Y-m-d',date('Y-m-d'))->format('m'),
	        	'YEAR' => DateTime::createFromFormat('Y-m-d',date('Y-m-d'))->format('Y'),
	        	'submitButton' => 'Tampilkan'
	    	];

	    	$postdata = http_build_query($arrPostData);

	    	curl_setopt($ch, CURLOPT_URL, $form->action);
	    	curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
	    	curl_setopt($ch, CURLOPT_POST, 1);
	    	$result = curl_exec($ch);
	    	file_put_contents("mutasi.html",$result);
	    	return $this->dataMutasi();
		}
	}

	public function dataMutasi(){
        $html = new simple_html_dom();
        $result = file_get_contents("mutasi.html");
        $html->load($result);

        try{
            $table = $html->getElementById('tabel-saldo');
            if(empty($table)){
            	$error = [
            		'status' => 'false',
            		'message' => '"no data"'
            	];

                return json_encode($error);
            }

            $tbody = $table->children(1);

            $data = [];

            foreach($tbody->children() as $tr){
                $tgl = !empty($tr->children(0))?$tr->children(0)->innertext():"";
                $judul = !empty($tr->children(1))?strip_tags($tr->children(1)->innertext()):"";
                $nominal1 = !empty($tr->children(2))?$tr->children(2)->innertext():"";
                $nominal2 = !empty($tr->children(3))?$tr->children(3)->innertext():"";
                $nominal3 = !empty($tr->children(4))?$tr->children(4)->innertext():"";
               
                $data[] = [
                   'tgl'	=> $tgl,
                   'judul'	=> $judul,
                   'keluar'	=> $this->fixAngka($nominal1),
                   'masuk'	=> $this->fixAngka($nominal2),
                   'saldo' => $this->fixAngka($nominal3)
                ];
            }

            $toBeDeleted[] = 0;
            
            $total = count($data);
            $endOffsetDelete = $total - 4;
            for($i = $total; $i > $endOffsetDelete; $i--){
                $toBeDeleted[] = $i;
            }

            foreach($toBeDeleted as $tbd){
                unset($data[$tbd]);
            }

            return json_encode($data);
        }catch(\Exception $e){
            return $e->getMessage();
        }
        

    }

    private function fixAngka($string){
        if(!is_null($string)){
            $string = substr($string, 0, -3);
            $string = str_replace('.', '', $string);
            return (int)$string;
        }
        return 0;
    }

	public function logout(){
		$ch = curl_init();
    	define("COOKIE_FILE", "cookie.txt");
    	curl_setopt($ch, CURLOPT_URL, 'https://ib.bri.co.id/ib-bri/Logout.html');
		curl_setopt($ch, CURLOPT_COOKIEJAR, COOKIE_FILE);
		curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIE_FILE);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.75 Safari/537.36");
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    	$result = curl_exec($ch);
    	return $result;
	}
}

$bri = new bri();

$csrf = $bri->prepare();
$cap = $bri->getCap();
$bri->login($csrf, $cap);
$mutasi = $bri->prepareMutasi();
file_put_contents("hasil_mutasi.json",$mutasi);
$bri->logout();
$json = file_get_contents("hasil_mutasi.json");
// echo $json;
$time_start = microtime(true);
while (json_decode($json, true)['status'] == 'false') {
	$csrf = $bri->prepare();
	$cap = $bri->getCap();
	$bri->login($csrf, $cap);
	$mutasi = $bri->prepareMutasi();
	file_put_contents("hasil_mutasi.json",$mutasi);
	$bri->logout();
	$json = file_get_contents("hasil_mutasi.json");
	if (json_decode($json, true)['status'] !== 'false') {
		echo file_get_contents("hasil_mutasi.json");
		exit;
	}

	$time_end = microtime(true);
	$time = $time_end - $time_start;
	$time = explode('.', $time)[0];

	if ($time > 10) {
		$data = [
			'status' => true,
			'message'=> "limit $time seconds"
		];
		echo json_encode($data);
		exit;
	}

}

echo file_get_contents("hasil_mutasi.json");

?>
