<?php
$filename = $_GET['filename'] ? $_GET['filename'] : 'sample/render_to_jpeg.jpg';

ob_start();
set_time_limit(0);
?>
<!DOCTYPE html>
<html>
<head>
	<title>JPEG RENDER</title>
	<style type="text/css">
	body {line-height:18px; font-size:11px; font-family:verdana, '돋움';}
	.n16 {font-family:consolas; font-size:14px; float:left; margin-left:15px; width:10px;}
	.n16.n8 {margin-right:20px;}
	.px {float:left; width:1px; height:1px; font-size:1px;}
	</style>
	<script type="text/javascript" src="../gallery/jquery.js"></script>
	<script type="text/javascript">
	function set(objValue) {
		objValue = objValue.innerHTML;
		document.getElementById('jpeg').filename.value = objValue;
		return false;
	}
	</script>
	<meta http-equiv="X-UA-Compatible" content="requiresActiveX=true" />
</head>
<body>
<!-- 파일 선택 -->
<a href="#" onclick="return set(this);">sample/ArticleRead.jpg</a>
<a href="#" onclick="return set(this);">sample/ArticleRead2.jpg</a>
<a href="#" onclick="return set(this);">sample/render_to_jpeg.jpg</a>
<a href="#" onclick="return set(this);">sample/render_to_jpeg2.jpg</a>
<a href="#" onclick="return set(this);">sample/render_to_jpeg3.jpg</a>
<a href="#" onclick="return set(this);">sample/search.jpg</a>
<!--<a href="#" onclick="return set(this);">sample/RE_Baram0.jpg</a>-->
<a href="#" onclick="return set(this);">sample/test.jpg</a>
<form id="jpeg" action="" method="get">
	<input type="text" name="filename" value="" placeholder="파일이름" style="font-size:11px;" />
	<input type="submit" value="JPEG RENDER" style="font-size:11px;" />
</form>
<h1 style="color:#f00;">HTML5를 사용하지 않고 이미지 효과 주기</h1>
<?php
define('DCTSIZE', 8);
define('DCTSIZE2', 64);

// DCT계수 * 양자화 테이블값
function q_table($n, $m) {
	$t = array();
	for ($i = 0; $i < 8; $i++) {
		$t[$i] = $n[$i] * hexdec($m[$i]);
	}
	return $t;
}
class fmJPEG {
	var $php_time = 0;
	var $filename = '';
	var $dataArr = array();
	var $dataLength = 0;
	var $markerArr = array();
	var $markerDesc = array();
	var $huffmanCode = array();
	var $idxx = array();
	var $arr = array('0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f');

	// 생성자
	function fmJPEG($filename = '') {
		$this->filename = $filename;
		$this->render();
	}

	// 10 -> 16진수
	function dec16($dec) {
		if (!isset($this->idxx[$dec]))
			$this->idxx[$dec] = $this->arr[$dec/16].$this->arr[$dec%16];

		return $this->idxx[$dec];
	}

	// RGB 데이터로 사용 가능하도록 변형
	function intRGB($n, $f=false) {
		// 반올림 정확도 증가
		//for ($i = 1000000; $i >= 1; $i /= 10) {
		//	$n *= $i;
			$n = round($n);
		//	$n /= $i;
		//}

		if ($f == false) {
			// 0 ~ 255 로 변형
			if ($n < 0) $n = 0;
			if ($n > 255) $n = 255;
		}
		return $n;
	}

	// JPEG RENDER 함수
	function render() {
		$fileData = file_get_contents($this->filename);
		$dataLen = strlen($fileData);
		$this->dataLength = $dataLen;

		$debugText = '';
		for ($i = 0; $i < $dataLen; $i++) {
			// 데이터를 실수형으로 변환
			$this->dataArr[$i] = dechex(ord($fileData[$i]));
			// 0 => `00` 으로 변환
			if ($this->dataArr[$i] == '0') $this->dataArr[$i] = '00';
			// 16미만의 수를 0X 꼴로 변환
			if (strlen($this->dataArr[$i]) == 1) $this->dataArr[$i] = '0'.$this->dataArr[$i];
		}

		// MarkerPointerIndex를 0으로 초기값 셋팅
		$this->setMarkerPointerIndex();

		// JFIF segment format
		$this->setMarker('Start of image 0xFFD8', 0xffd8);
		$this->setMarker('Always equals 0xFFE0', 0xffe0);
		$ffe0 = $this->getMarker('ffe0');
		$ffe0 = $ffe0[0];
		$this->set('jfif::app0', substr($ffe0, 0, 4), 'Always equals 0xFFE0');
		$this->set('jfif::length', substr($ffe0, 4, 4), 'Length of segment excluding APP0 marker');
		$this->set('jfif::identifier', substr($ffe0, 8, 10), 'Always equals "JFIF" (with zero following) (0x4A46494600)');
		$this->set('jfif::version', substr($ffe0, 18, 4), 'First byte is major version (currently 0x01). second byte is minor version (currently 0x02)');
		$this->set('jfif::density_units', substr($ffe0, 22, 2), 'Units for pixel density fields.<br />0-No units, aspect ratio only specified<br />1-Pixels per inch<br />2-Pixels per centimetre');
		$this->set('jfif::x_density', substr($ffe0, 24, 4), 'Integer horizontal pixel density');
		$this->set('jfif::y_density', substr($ffe0, 28, 4), 'Integer vertical pixel density');
		$this->set('jfif::thumbnail_width', substr($ffe0, 32, 2), 'Horizontal size of embedded JFIF thumbnail in pixels');
		$this->set('jfif::thumbnail_height', substr($ffe0, 34, 2), 'Vertival size of embedded JFIF thumbnail in pixels');
		$this->set('jfif::thumbnail_data', substr($ffe0, 36), 'Uncompressed 24 bit RGB raster thumbnail');

		// DQT marker 읽어오기 - 최대 4개의 DQT table을 담을수 있다
		$dqtLen = 0;
		for ($i = 0; $i < 4; $i++) {
			$this->setMarker('DQT Marker only 0xFFDB', 0xffdb);
			$ffdb = $this->getMarker('ffdb');
			$ffdb = $ffdb[0];
			if ($ffdb != '') {
				$this->set('dqt::dqt'.$i, substr($ffdb, 0, 4), 'DQT Marker only 0xFFDB');
				$this->set('dqt::lq'.$i, substr($ffdb, 4, 4), 'Length of Quantization table header - Quantization table header size');
				$this->set('dqt::pq'.$i, substr($ffdb, 8, 1), 'Precision of Quantization table');
				$this->set('dqt::tq'.$i, substr($ffdb, 9, 1), 'Table number of Quantization table');
				$this->set('dqt::table'.$i, substr($ffdb, 10), 'Quantization table');
			}
		}

		//SOFn Marker 0xFFCn (0xFFC0 ~ 0xFFCF)
		$this->setMarker('SOF0 Marker (0xFFC0) : 0xFFC0~0xFFCF', 0xffc0);
		$ffc0 = $this->getMarker('ffc0');
		$ffc0 = $ffc0[0];
		$this->set('sofn::sofn', substr($ffc0, 0, 4), 'SOF0 Marker (0xFFC0) : 0xFFC0~0xFFCF');
		$this->set('sofn::lf', substr($ffc0, 4, 4), 'Length of Frame header size');
		$this->set('sofn::p', substr($ffc0, 8, 2), 'Precision - Image sample quantization bit');
		$this->set('sofn::y', substr($ffc0, 10, 4), 'Height - Image height');
		$this->set('sofn::x', substr($ffc0, 14, 4), 'Width - Image width');
		$this->set('sofn::nf', substr($ffc0, 18, 2), 'Number of Component of Frame');
		$sof_nf = $this->getMarker('sofn::nf');
		for ($i = 0; $i < $sof_nf[0]; $i++) {
			$this->set('sofn::c'.$i, substr($ffc0, 20+($i*6), 2), 'Component Number');
			$this->set('sofn::h'.$i, substr($ffc0, 22+($i*6), 1), 'H Factor');
			$this->set('sofn::v'.$i, substr($ffc0, 23+($i*6), 1), 'V Factor');
			$this->set('sofn::qt'.$i, substr($ffc0, 24+($i*6), 2), 'Quantization Table Number');
		}

		// DHT Marker ReadFunction 새로운 함수로 파싱
		// DHT Marker (Define Huffman Tables) DC 2개, AC 2개
		$this->setMarkerPointerIndex();
		for ($i = 0; $i < 4; $i++) {
			$this->setMarker('Define Huffman Tables', 0xffc4);
			$ffc4 = $this->getMarker('ffc4');
			$ffc4 = $ffc4[0];
			// DHT MarkerCode
			$this->set('dht::dht'.$i, substr($ffc4, 0, 4), 'Define Huffman Tables');
			$this->set('dht::dl'.$i, substr($ffc4, 4, 4), 'Data Length');
			$this->set('dht::tc'.$i, substr($ffc4, 8, 1), 'Table class, 0-DC, 1-AC');
			$this->set('dht::ti'.$i, substr($ffc4, 9, 1), 'Table ID, 0 ~ 3');
			$this->set('dht::cl'.$i, substr($ffc4, 10, 32), 'Codelength Counter');
			// Codelength Counter의 총합
			$dhtLen = 0;
			$dht_cl = $this->getMarker('dht::cl'.$i);
			for ($j = 0; $j < 16; $j++) {
				$dht_cl_len = hexdec(substr($dht_cl[0], $j*2, 2));
				$dhtLen += $dht_cl_len;
			}
			$this->set('dht::sb'.$i, substr($ffc4, 42, $dhtLen*2), 'Symbol Data');
		}

		// SOS Marker
		// SOS 시작 마커 인덱스 구하기
		$sos_pointerIndex = $this->getMarkerPointerIndex();
		$this->setMarker('SOS Marker only 0xFFDA', 0xffda);
		$ffda = $this->getMarker('ffda');
		$ffda = $ffda[0];
		$this->set('sos::sos', substr($ffda, 0, 4), 'SOS Marker only 0xFFDA');
		$this->set('sos::ls', substr($ffda, 4, 4), 'Length of Scan header size');
		$this->set('sos::ns', substr($ffda, 8, 2), 'Number of Component of Scan');
		$sos_ns = $this->getMarker('sos::ns');
		$n = hexdec($sos_ns[0]);
		for ($i = 0; $i < $n; $i++) {
			$this->set('sos::cs'.$i, substr($ffda, 10+($i*4), 2), 'Component Number');
			$this->set('sos::td'.$i, substr($ffda, 12+($i*4), 1), 'DC Huffman Table Number');
			$this->set('sos::ta'.$i, substr($ffda, 13+($i*4), 1), 'AC Huffman Table Number');

			$dqtLen += 2;
		}
		$this->set('sos::ss', substr($ffda, 14+(($n-1)*4), 2), 'Spectral Selection Start');
		$this->set('sos::se', substr($ffda, 16+(($n-1)*4), 2), 'Spectral Selection End');
		$this->set('sos::ah', substr($ffda, 18+(($n-1)*4), 1), 'Successive Approximation High');
		$this->set('sos::al', substr($ffda, 19+(($n-1)*4), 1), 'Successive Approximation Low');

		// ScanData
		// SOS MarkerPointer 이전으로 포인터 되돌리기
		$this->setMarkerPointerIndex($sos_pointerIndex);
		$this->setMarker('ECS (Entropy Coded Signal) Decoding - ScanData', 0xffda, 0xffd9);
		$scan_data = $this->getMarker('ffdaffd9');
		$this->set('scan::data', $scan_data[0], 'ECS (Entropy Coded Signal) Decoding - ScanData');

		$this->setMarker('EOI Marker (End of Image) only 0xFFD9', 0xffd9);
	}

	var $fileData = '';
	var $markerPointerIndex = 0;
	// setMarker 함수의 진행 데이터포인터를 $pointerIndex값으로 되돌립니다.
	function setMarkerPointerIndex($pointerIndex=0) {
		$this->markerPointerIndex = $pointerIndex;
	}
	// setMarker 함수의 진행 데이터포인터를 되돌립니다.
	function getMarkerPointerIndex() {
		return $this->markerPointerIndex;
	}
	// @$description = '해당 마커 데이타에 대한 설명'
	// @$sMarkerCode = '시작 마커코드 10진수' ex) 0xffda
	// @$eMarkerCode = '종료 마커코드 10진수' ex) 0xffd9
	//	이값을 설정할 경우 $sMarkerCode종료 ~ $eMarkerCode시작 까지의 모든 hex문자열을 가져옵니다.
	function setMarker($description, $sMarkerCode, $eMarkerCode='') {
		$temp = 0;
		$idx = 0;
		$resultStart = false;
		$result = array();
		$sMarker = dechex($sMarkerCode);
		$eMarker = ($eMarkerCode != '')? dechex($eMarkerCode): '';
		$sMarkerData = $sMarker[2].$sMarker[3];
		$eMarkerData = $eMarker[2].$eMarker[3];
		$markerLength = 0;
		for ($i = $this->markerPointerIndex; $i < $this->dataLength; $i++) {
			// 해당 시작Marker 데이터가 끝날 경우
			if ($eMarker == '' && $markerLength > 0 && $idx == $markerLength) {
				break;
			}
			if ($this->dataArr[$i] == 'ff') {
				// 찾으려는 MarkerCode의 데이타가 맞다면!
				if ($sMarkerData == $this->dataArr[$i+1]) {
					// 코드길이 계산 (Byte단위)
					$markerLength = hexdec($this->dataArr[$i+2].$this->dataArr[$i+3]) + 2/*MarkerCode 2Byte*/;
					// StartMarkerCode 종료 ~ EndMarkerCode 시작 까지의 문자열 저장
					if ($eMarker != '') $resultStart = true;
				} else if ($eMarkerData == $this->dataArr[$i+1]) {
					// 종료 마커와 같을 경우
					$resultStart = false;
				}
			}
			// MarkerData 저장
			if ($idx < $markerLength) {
				if ($eMarker == '') {
					$result[$idx] = $this->dataArr[$i];
					$this->markerPointerIndex = $i;
				}
				$idx++;
			} else {
				// StartMarkerCode 종료 ~ EndMarkerCode 시작 까지의 문자열 저장
				if ($resultStart == true && $eMarker != $this->dataArr[$i+1].$this->dataArr[$i+2]) {
					$result[$idx++] = $this->dataArr[$i];
					$this->markerPointerIndex = $i;
				}
			}

			// Marker 코드만 존재하고 데이터정보는 없을 경우
			// 해당 StartMarkerCode만 리턴
			if ($markerLength > 0) {
				if ($this->dataArr[$i-1] == 'ff' &&
					$this->dataArr[$i+1] == 'ff' &&
					$this->dataArr[$i] != '00')
					break;
				if ($eMarker != '' && $resultStart == false) {
					break;
				}
			}
		}
		// MarkerData 정보 설정
		$this->set($sMarker.$eMarker, implode('', $result), $description);
	}
	// setMarker Data
	function set($field, $data, $description = '') {
		$this->markerArr[$field] = $data;
		$this->markerDesc[$field] = $description;
	}

	// getMarker Data
	function getMarker($field) {
		$field_data_len = strlen($this->markerArr[$field]);
		$byte_data = array();
		for ($i = 0; $i < $field_data_len; $i += 2) {
			$byte_data[] = substr($this->markerArr[$field], $i, 2);
		}
		$byte_str = str_replace('ff 00', 'ff', implode(' ', $byte_data));
		$byte_str = str_replace(' ', '', $byte_str);
		$this->markerArr[$field] = $byte_str;

		$data = array(
				$this->markerArr[$field],
				$this->markerDesc[$field]
				);
		return $data;
	}

	// Huffman Decoding
	var $huffmanCodeX = array();
	//var $maxHuffmanLength = array();
	function decHuffman($len, $data) {
		$code = array();
		$codeXX = array();
		$t = 0;
		$b = 0;
		$x = 1;
		$o = 0;
		$bits = '';
		$maxLen = 0;

		$total = 0;
		$idx = 0;
		$element = array();
		$length = array();
		// 전체 Huffman code size 구하기
		for ($i = 0; $i < 16; $i++) {
			$total += hexdec(substr($len, $i*2, 2));
		}

		$dx = 0;
		$arrX = array();
		$bArrX = array('');
		$tTotal = 0;
		for ($i = 0; $i < 16; $i++) {
			$length[$i] = hexdec(substr($len, $i*2, 2));
			$element[$i] = substr($data, $idx, $length[$i]*2);
			$tTotal = ($tTotal >= $total) ? $tTotal + 1 : $tTotal + $length[$i];
			if ($tTotal <= $total) {
				$idx += $length[$i]*2;
				$eleLen = strlen($element[$i]) / 2;
				// bit 증가
				$bits = $bits.'0';
				$o = $eleLen;
				$x = $x * 2 - $o;
				$t = $b + $o + $x;
				// huffman code 구하기
				$b = $b + $o;
				$ox = $o + $x;
				for ($j = 0; $j < $ox; $j++) {
					if ($j % 2 == 0) {
						$arrX[$j] = $bArrX[$dx].'0';
					} else {
						$arrX[$j] = $bArrX[$dx++].'1';
					}
				}
				// huffman code
				for ($j = 0; $j < $o; $j++) {
					$ele = hexdec(substr($element[$i], $j*2, 2));
					//$code[$ele] = $arrX[$j];
					//$codeX[$arrX[$j][0]][$ele] = $arrX[$j];
					$tmpCodeXX = &$codeXX;
					$hufSize = strlen($arrX[$j]);
					for($jj = 0; $jj < $hufSize; $jj++) {
						$tmpCodeXX = &$tmpCodeXX[$arrX[$j][$jj]];
						if ($jj == $hufSize-1) {
							$tmpCodeXX = $ele;
						}
					}
					//if ($maxLen < strlen($arrX[$j])) $maxLen = strlen($arrX[$j]);
					unset($arrX[$j]);
				}
				// 이전 node값 설정
				$bArrX = array();
				foreach ($arrX as $v) {
					$bArrX[] = $v;
				}
				$arrX = array();
				$dx = 0;
			}
		}
		// huffman table 구하기
		$this->huffmanCode[] = &$codeXX;
		//$this->maxHuffmanLength[] = $maxLen;
	}

	// 해당 AC Component의 값을 구하는 함수
	// $ac_component = 'AC Value를 구할 2진수의 데이터'
	function getAcValue($ac_component) {
		$ac_value = 0;
		$cate = strlen($ac_component);
		// 해당 cate의 AC Value 개수
		$ac_count = pow(2, $cate);
		$ac_min_value = $ac_count / 2;
		$ac_max_value = $ac_count - 1;
		$ac_component_bin = bindec($ac_component);
		if (($ac_count / 2) > $ac_component_bin) {
			$ac_value = $ac_max_value * (-1) + $ac_component_bin;
		} else {
			$ac_value = $ac_min_value + ($ac_component_bin - $ac_min_value);
		}
		return $ac_value;
	}

	// 해당 DC Component의 값을 구하는 함수
	// $dc_component = 'DC Value를 구할 2진수의 데이터'
	function getDcValue($dc_component) {
		$dc_value = 0;
		$cate = strlen($dc_component);
		// 해당 cate의 dc Value 개수
		$dc_count = pow(2, $cate);
		$dc_min_value = $dc_count / 2;
		$dc_max_value = $dc_count - 1;
		$dc_component_bin = bindec($dc_component);
		if (($dc_count / 2) > $dc_component_bin) {
			$dc_value = $dc_max_value * (-1) + $dc_component_bin;
		} else {
			$dc_value = $dc_min_value + ($dc_component_bin - $dc_min_value);
		}
		return $dc_value;
	}

	// 2013-03-03
	// DC 정보값이 3번째 부터 잘못 읽히고 있음
	// 2013-03-10 수정완료
	// @$type = 'Y or CrCb'
	// @$seconds_data = '2진수 ECS Data
	var $bfrYCbCr = array('Y'=>0, 'Cb'=>0, 'Cr'=>0);
	var $s = '';
	function getMCU_YCbCr($type, &$seconds_data, $huffmanYCbCr, $hFactorArr, $vFactorArr) {
		$dc = $huffmanYCbCr[$type][0];
		$ac = $huffmanYCbCr[$type][1];
		$dcTypeName = $dc*2;
		$acTypeName = $ac*2+1;
		// MCU 정보
		// DC = 0, 2
		// AC = 1, 3
		//$this->huffmanCode[0] == DC 0
		//$this->huffmanCode[1] == AC 0
		//$this->huffmanCode[2] == DC 1
		//$this->huffmanCode[3] == AC 1
		$next_data = '';
		// 값 초기화
		$arrData = array();

		// $k = huffmanCode decoding value
		// $v = huffmanCode
		$k = 0;
		$v = '';
		$huffTree = $this->huffmanCode[$dcTypeName];
		for ($idx = 0; ; $idx++) {
			if (!is_array($huffTree)) {
				// 디코딩된 값 구하기
				$k = $huffTree;
				break;
			} else {
				$huffTree = &$huffTree[$seconds_data[$idx]];
				// 허프만 코드값 구하기
				$v .= $seconds_data[$idx];
			}
		}

		// DC Component Data
		$dc_v_len = strlen($v);
		$dc_component_data = substr($seconds_data, $dc_v_len, $k);
		// 다음 코드값들
		$next_data = substr($seconds_data, $dc_v_len + $k);
		//$dc_component = bindec($dc_component_data);
		//echo "ZRL =[\t0] Val=[\t".$this->getDcValue($dc_component_data)."]\tCoef=[00=DC] (".$v.$dc_component_data.")\n";
		$arrData[0] = $this->getDcValue($dc_component_data);

		// AC Value 구하는 정보 URL
		// http://www.stanford.edu/class/ee398a/handouts/lectures/08-JPEG.pdf
		// https://docs.google.com/viewer?a=v&q=cache:n2RCiJQoiB4J:picaso.hannam.ac.kr/courses/Undergraduate/mediainfoproc/Lectnote/2002/chap09/%25EC%25A0%2595%25EC%25A7%2580%25EC%2598%2581%25EC%2583%2581%25EC%2595%2595%25EC%25B6%2595%25EB%25B6%2580%25ED%2598%25B8%25ED%2599%2594.pdf+JPEG+Dequantization+dc+code&hl=ko&gl=kr&pid=bl&srcid=ADGEEShWTsdE29a7-gPTAQdM6QjOleMruE-P2urqQ5uo-zQ5maAz1Fa-_5Mcg6yxzo42tvooGb0VyE5JwCfWecnTNHjg853o210AUE2BRlFhKpJ9oY-rHK1nSPFwnygWE00zhOAmTm4D&sig=AHIEtbQSQoMlND5SQhv4teiK6ugyfbbRIg
		//$debugIdx = 1;
		//$huffmanCodeCount = count($this->huffmanCode[$acTypeName]);
		// DC값을 제외한 63개의 AC값 구하기
		for ($i = 0; $i < 63; $i++) {
			$this->loop_count++;
			$this->mcu_loop_count++;

			// AC값을 읽을 데이터가 없을경우 루프 탈출
			if ($next_data == '') break;
			//----------------------------------
			// 여기부터 아래로 쭉~
			// $k = huffmanCode decoding value
			// $v = huffmanCode
			$k = 0;
			$v = '';
			$huffTree = &$this->huffmanCode[$acTypeName];
			for ($idx = 0; ; $idx++) {
				if (!is_array($huffTree)) {
					// 디코딩된 값 구하기
					$k = $huffTree;
					break;
				} else {
					$huffTree = &$huffTree[$next_data[$idx]];
					// 허프만 코드값 구하기
					$v .= $next_data[$idx];
				}
			}

			$rlcCount = 0;
			$ac_huf = $this->dec16($k);
			// AC Component Data
			$ac_v_len = strlen($v);
			// 다음 코드값들
			$next_data = substr($next_data, $ac_v_len);
			if ($ac_huf != '00') {
				//$ac_component_data = substr($next_data, 0, $ac_huf[1]);
				//$ac_component = bindec($ac_component_data);
				//echo 'MCU'.$n.' '.$type.' channel, AC component : ('.$ac_huf[0].','.$ac_huf[1].')<br />';
				// Run length : 이전 Zigzag Table의 0의 개수
				// Size in bits : AC Value Data
				// $ac_huf[1] 층의 AC coefficient values값들 구하기
				//$ac_huf_count = pow(2, $ac_huf[1]); // $ac_huf[1]층의 값의 수
				//$ac_code_length = $ac_huf_count / 2;
				$ac_code = substr($next_data, 0, $ac_huf[1]);

				// 다음 코드값들
				if ($ac_huf[1] > 0) $next_data = substr($next_data, $ac_huf[1]);

				if ( $ac_huf == 'f0') { // ZRL
					//$debugIdx += hexdec($ac_huf[0]);
					//echo "ZRL =[\t15] Val=[\t0]\tCoef=[".($debugIdx-hexdec($ac_huf[0]))."...".$debugIdx."] (".$v.$ac_code.")\n";
					//$debugIdx++;
					// RLC Setting 0 -> 16개
					for ($j = 0; $j < 16; $j++) {
						$arrData[] = 0;
						$rlcCount++;
					}
				} else {
					//$debugIdx += hexdec($ac_huf[0]);
					//echo "ZRL =[\t".hexdec($ac_huf[0])."] Val=[\t".$this->getAcValue($ac_code)."]\tCoef=[".($debugIdx-hexdec($ac_huf[0]))."...".$debugIdx."] (".$v.$ac_code.")\n";
					//$debugIdx++;

					// RLC Setting
					$rlc0 = hexdec($ac_huf[0]);
					for ($j = 0; $j < $rlc0; $j++) {
						$arrData[] = 0;
						$rlcCount++;
					}
					$arrData[] = $this->getAcValue($ac_code);
					$rlcCount++;
				}
			} else { // EOB
				//$debugIdx += hexdec($ac_huf[0]);
				//echo "ZRL =[\t0] Val=[\t0]\tCoef=[".($debugIdx-hexdec($ac_huf[0]))."...".$debugIdx."] (".$v.") EOB\n";
				for ($j = $arrCount; $j < 64; $j++) {
					$arrData[$j] = 0;
					$rlcCount++;
				}
			}
			$i += $rlcCount - 1;

			$arrCount = count($arrData);
			if ($arrCount > 63) break;
		}
		// 다음 값 최신화
		$seconds_data = $next_data;
		//echo "\n";

		if ($type != 'Y' && $hFactorArr['Y']*$vFactorArr['Y'] > 1) {
			if ($hFactorArr['Y']*$vFactorArr['Y'] == 1) {
				$arrDataArr[0] = $this->getZigzag($arrData);
				$arrDataArr[1] = $this->getZigzag($arrData);
				$arrDataArr[2] = $this->getZigzag($arrData);
				$arrDataArr[3] = $this->getZigzag($arrData);
			} else {
				$arrDataX = array();
				$arrDataArr = array();
				// CbCr 값 재 배열
				for ($iy = 0; $iy < 16; $iy++) {
					for ($ix = 0; $ix < 16; $ix++) {
						if ($iy >= 0 && $iy <= 7 && $ix >= 0 && $ix <= 7)
						{
							$arrDataX[$iy*2+0][$ix*2+0] = $arrData[$iy*8+$ix];
							$arrDataX[$iy*2+0][$ix*2+1] = 0;
							$arrDataX[$iy*2+1][$ix*2+0] = 0;
							$arrDataX[$iy*2+1][$ix*2+1] = 0;

							$arrDataArr[0][$iy*8+$ix] = $arrDataX[$iy][$ix];
						}
						if ($iy >= 8 && $iy <= 15 && $ix >= 0 && $ix <= 7)
						{
							$arrDataArr[2][($iy-8)*8+$ix] = $arrDataX[$iy][$ix];
						}
						if ($iy >= 0 && $iy <= 7 && $ix >= 8 && $ix <= 15)
						{
							$arrDataArr[1][$iy*8+$ix-8] = $arrDataX[$iy][$ix];
						}
						if ($iy >= 8 && $iy <= 15 && $ix >= 8 && $ix <= 15)
						{
							$arrDataArr[3][($iy-8)*8+$ix-8] = $arrDataX[$iy][$ix];
						}
					}
				}
				$arrDataArr[0] = $this->getZigzag($arrDataArr[0]);
				$arrDataArr[1] = $this->getZigzag($arrDataArr[1]);
				$arrDataArr[2] = $this->getZigzag($arrDataArr[2]);
				$arrDataArr[3] = $this->getZigzag($arrDataArr[3]);
			}
		}

		//echo '<h2>Zig zag coding</h2>';
		if ($type == 'Y')
			return $this->getZigzag($arrData); // 1 : DC Value, 63 : AC Value
		else
			return $arrDataArr;
	}

	// @$seconds_data = '2진수 ECS Data
	// @$hFactorArr = array('Y'=>셈플링주기, 'Cb'=>셈플링주기, 'Cr'=>셈플링주기);
	// @$vFactorArr = array('Y'=>셈플링주기, 'Cb'=>셈플링주기, 'Cr'=>셈플링주기);
	// @$huffmanYCbCr =	array('Y'=>DC, AC ID, 'Cb'=>DC, AC ID, 'Cr'=>DC, AC ID);
	function getMCU(&$seconds_data, $hFactorArr, $vFactorArr, $huffmanYCbCr) {
		$data = array();
		// 주기에 따른 인터리브 & 비인터리브 방식
		// Y : DC, AC
		for ($j = 0; $j < $vFactorArr['Y']; $j++) {
			for ($i = 0; $i < $hFactorArr['Y']; $i++) {
				$data['Y'][$j*$vFactorArr['Y']+$i] = $this->getMCU_YCbCr('Y', $seconds_data, $huffmanYCbCr, $hFactorArr, $vFactorArr);
			}
		}
		$adata = array();
		for ($j = 0; $j < $vFactorArr['Cb']; $j++) {
			for ($i = 0; $i < $hFactorArr['Cb']; $i++) {
				if ($vFactorArr['Y']*$hFactorArr['Y'] == 1) {
					$data['Cb'][$j*$vFactorArr['Cb']+$i] = $this->getMCU_YCbCr('Cb', $seconds_data, $huffmanYCbCr, $hFactorArr, $vFactorArr);
				} else {
					$adata = $this->getMCU_YCbCr('Cb', $seconds_data, $huffmanYCbCr, $hFactorArr, $vFactorArr);
					$data['Cb'][($j*$vFactorArr['Cb']+$i)*4+0] = $adata[0];
					$data['Cb'][($j*$vFactorArr['Cb']+$i)*4+1] = $adata[1];
					$data['Cb'][($j*$vFactorArr['Cb']+$i)*4+2] = $adata[2];
					$data['Cb'][($j*$vFactorArr['Cb']+$i)*4+3] = $adata[3];
				}
			}
		}
		$adata = array();
		for ($j = 0; $j < $vFactorArr['Cr']; $j++) {
			for ($i = 0; $i < $hFactorArr['Cr']; $i++) {
				if ($vFactorArr['Y']*$hFactorArr['Y'] == 1) {
					$data['Cr'][$j*$vFactorArr['Cr']+$i] = $this->getMCU_YCbCr('Cr', $seconds_data, $huffmanYCbCr, $hFactorArr, $vFactorArr);
				} else {
					$adata = $this->getMCU_YCbCr('Cr', $seconds_data, $huffmanYCbCr, $hFactorArr, $vFactorArr);
					$data['Cr'][($j*$vFactorArr['Cr']+$i)*4+0] = $adata[0];
					$data['Cr'][($j*$vFactorArr['Cr']+$i)*4+1] = $adata[1];
					$data['Cr'][($j*$vFactorArr['Cr']+$i)*4+2] = $adata[2];
					$data['Cr'][($j*$vFactorArr['Cr']+$i)*4+3] = $adata[3];
				}
			}
		}
		return $data;
	}

	// IDCT 함수 수행
	// 공식참조
	// http://yahon.tistory.com/189
	// http://stackoverflow.com/questions/4240490/problems-with-dct-and-idct-algorithm-in-java
	// Fast IDCT 공식
	// http://iamaman.tistory.com/475
	// http://samplecodebank.blogspot.kr/2011/05/idct-example-cc.html
	// fast-IDCT
	// http://read.pudn.com/downloads183/sourcecode/math/859035/idct.c__.htm
	var $loop_count = 0;
	var $idct_loop_count = 0;
	var $mcu_loop_count = 0;

	// 양자화 테이블 설정
	var $Q = array();
	function setQtable($Q) {
		$this->Q = $Q;
	}
	// 역양자화 -> IDCT 실행
	function idct4096($S, &$s) {
		$N = 8;
		$s = array(array(0));
		// 양자화 테이블 값 곱하기
		$sS = array_map('q_table', $S, $this->Q);

		$cv = 0;
		$cu = 0;
		$s_tmp = 0;
		for ($y = 0; $y < 8; $y++) {
			for ($x = 0; $x < 8; $x++) {
				$s[$y][$x] = 0.0;
				for ($v = 0; $v < $N; $v++) {
					$s_tmp = 0.0;
					for ($u = 0; $u < $N; $u++) {
						$this->loop_count++;

						// 0.70710678118655 = 1/1.4142135623731 = 1/sqrt(2.0)
						$cv = ($v == 0)? 0.70710678118655: 1;
						$cu = ($u == 0)? 0.70710678118655: 1;

						$s_tmp += $cv * $cu * $sS[$v][$u] * cos((M_PI*(2*$x+1)*$u)/(2.0 * $N));
					}
					$s[$y][$x] += $s_tmp * cos((M_PI*(2*$y+1)*$v)/(2.0 * $N));
				}
				$s[$y][$x] *= 0.25;

				// 소수점 반올림 + 128 = 0 ~ 255
				$s[$y][$x] = $this->intRGB($s[$y][$x] + 128);

				// 사용불가능 값 수정
				//if ($s[$y][$x] < 0) $s[$y][$x] = 0;
				//if ($s[$y][$x] > 255) $s[$y][$x] = 255;
			}
		}
	}

	// FFT 빠른 역 이산코사인변환 공식 이용
	// Fast-IDCT 알고리즘 구현
	var $dct_table = array();
	function idct($S, &$s) {
		// 양자화 테이블 값 곱하기
		$S = array_map('q_table', $S, $this->Q);

		$tmp0 = $tmp1 = $tmp2 = $tmp3 = $tmp4 = $tmp5 = $tmp6 = $tmp7 = 0.0;
		$tmp10 = $tmp11 = $tmp12 = $tmp13 = 0.0;
		$z5 = $z10 = $z11 = $z12 = $z13 = 0.0;

		$inptr = array();
		$outptr = array();
		$quantptr = array();
		$wsptr = array();
		$ctr = 0;
		$workspace = array(); /* buffers data between passes */	 

		/* Pass 1: process columns from input, store into work array. */	 

		$qi = 0;
		$ii = 0;
		$wi = 0;
		$quantptr = &$this->dct_table;
		$wsptr = &$workspace; 
		for ($ctr = 8; $ctr > 0; $ctr--) {
			$this->loop_count++;
			$this->idct_loop_count++;

			/* Even part */
			$tmp0 = $S[intval(($ii+ 0)/8)][($ii+ 0)%8] *( $quantptr[$qi+ 0]);
			$tmp1 = $S[intval(($ii+16)/8)][($ii+16)%8] *( $quantptr[$qi+16]);
			$tmp2 = $S[intval(($ii+32)/8)][($ii+32)%8] *( $quantptr[$qi+32]);
			$tmp3 = $S[intval(($ii+48)/8)][($ii+48)%8] *( $quantptr[$qi+48]);

			$tmp10 = $tmp0 + $tmp2;	/* phase 3 */
			$tmp11 = $tmp0 - $tmp2;

			$tmp13 = $tmp1 + $tmp3;	/* phases 5-3 */
			$tmp12 = ($tmp1 - $tmp3) * 1.414213562 - $tmp13; /* 2*c4 */

			$tmp0 = $tmp10 + $tmp13; /* phase 2 */
			$tmp3 = $tmp10 - $tmp13;
			$tmp1 = $tmp11 + $tmp12;
			$tmp2 = $tmp11 - $tmp12;

			/* Odd part */

			$tmp4 = $S[intval(($ii+ 8)/8)][($ii+ 8)%8]*( $quantptr[$qi+ 8]);
			$tmp5 = $S[intval(($ii+24)/8)][($ii+24)%8]*( $quantptr[$qi+24]);
			$tmp6 = $S[intval(($ii+40)/8)][($ii+40)%8]*( $quantptr[$qi+40]);
			$tmp7 = $S[intval(($ii+56)/8)][($ii+56)%8]*( $quantptr[$qi+56]);

			$z13 = $tmp6 + $tmp5;		/* phase 6 */
			$z10 = $tmp6 - $tmp5;
			$z11 = $tmp4 + $tmp7;
			$z12 = $tmp4 - $tmp7;

			$tmp7 = $z11 + $z13;		 /* phase 5 */
			$tmp11 = ($z11 - $z13) * 1.414213562; /* 2*c4 */

			$z5 = ($z10 + $z12) * 1.847759065; /* 2*c2 */
			$tmp10 = 1.082392200 * $z12 - $z5; /* 2*(c2-c6) */
			$tmp12 = -2.613125930 * $z10 + $z5; /* -2*(c2+c6) */

			$tmp6 = $tmp12 - $tmp7;	/* phase 2 */
			$tmp5 = $tmp11 - $tmp6; 
			$tmp4 = $tmp10 + $tmp5; 

			$wsptr[$wi+ 0] = $tmp0 + $tmp7; 
			$wsptr[$wi+56] = $tmp0 - $tmp7; 
			$wsptr[$wi+ 8] = $tmp1 + $tmp6; 
			$wsptr[$wi+48] = $tmp1 - $tmp6; 
			$wsptr[$wi+16] = $tmp2 + $tmp5; 
			$wsptr[$wi+40] = $tmp2 - $tmp5; 
			$wsptr[$wi+32] = $tmp3 + $tmp4; 
			$wsptr[$wi+24] = $tmp3 - $tmp4; 

			$ii++;					/* advance pointers to next column */	 
			$qi++;
			$wi++;
		}	 

		/* Pass 2: process rows from work array, store into output array. */	 
		/* Note that we must descale the results by a factor of 8 == 2**3. */	 
		$wi = 0;
		$oi = 0;
		$wsptr = &$workspace; 
		$outptr = array(); 
		for ($ctr = 0; $ctr < 8; $ctr++) {	 
			$this->loop_count++;
			$this->idct_loop_count++;
			/* Even part */

			$tmp10 = $wsptr[$wi+0] + $wsptr[$wi+4]; 
			$tmp11 = $wsptr[$wi+0] - $wsptr[$wi+4]; 

			$tmp13 = $wsptr[$wi+2] + $wsptr[$wi+6]; 
			$tmp12 = ($wsptr[$wi+2] - $wsptr[$wi+6]) * 1.414213562 - $tmp13; 

			$tmp0 = $tmp10 + $tmp13; 
			$tmp3 = $tmp10 - $tmp13; 
			$tmp1 = $tmp11 + $tmp12; 
			$tmp2 = $tmp11 - $tmp12; 

			/* Odd part */	 

			$z13 = $wsptr[$wi+5] + $wsptr[$wi+3]; 
			$z10 = $wsptr[$wi+5] - $wsptr[$wi+3]; 
			$z11 = $wsptr[$wi+1] + $wsptr[$wi+7]; 
			$z12 = $wsptr[$wi+1] - $wsptr[$wi+7]; 

			$tmp7 = $z11 + $z13; 
			$tmp11 = ($z11 - $z13) * 1.414213562; 

			$z5 = ($z10 + $z12) * 1.847759065; /* 2*c2 */	 
			$tmp10 = 1.082392200 * $z12 - $z5; /* 2*(c2-c6) */	 
			$tmp12 = -2.613125930 * $z10 + $z5; /* -2*(c2+c6) */	 

			$tmp6 = $tmp12 - $tmp7; 
			$tmp5 = $tmp11 - $tmp6; 
			$tmp4 = $tmp10 + $tmp5; 

			/* Final output stage: scale down by a factor of 8 and range-limit */

			$s[$oi/8][0] = $this->intRGB(intval((intval($tmp0 + $tmp7) + 4)>>3) + 128); 
			$s[$oi/8][7] = $this->intRGB(intval((intval($tmp0 - $tmp7) + 4)>>3) + 128);
			$s[$oi/8][1] = $this->intRGB(intval((intval($tmp1 + $tmp6) + 4)>>3) + 128);
			$s[$oi/8][6] = $this->intRGB(intval((intval($tmp1 - $tmp6) + 4)>>3) + 128);
			$s[$oi/8][2] = $this->intRGB(intval((intval($tmp2 + $tmp5) + 4)>>3) + 128);
			$s[$oi/8][5] = $this->intRGB(intval((intval($tmp2 - $tmp5) + 4)>>3) + 128);
			$s[$oi/8][4] = $this->intRGB(intval((intval($tmp3 + $tmp4) + 4)>>3) + 128);
			$s[$oi/8][3] = $this->intRGB(intval((intval($tmp3 - $tmp4) + 4)>>3) + 128);

			$oi += 8;
			$wi += 8;		 /* advance pointer to next row */
		}
	}

	function idct_init() {
		static $aanscalefactor = array(
			1.0, 1.387039845, 1.306562965, 1.175875602,	 
			1.0, 0.785694958, 0.541196100, 0.275899379	 
		);
		
		/* For float AA&N IDCT method, multipliers are equal to quantization	
		 * coefficients scaled by scalefactor[row]*scalefactor[col], where	
		 *	 scalefactor[0] = 1	
		 *	 scalefactor[k] = cos(k*PI/16) * sqrt(2)		for k=1..7	
		 */	 

		$i = 0;
		for ($row = 0; $row < DCTSIZE; $row++) {
			for ($col = 0; $col < DCTSIZE; $col++) {
				$this->loop_count++;
				$this->idct_loop_count++;

				$this->dct_table[$i] = ($aanscalefactor[$row] * $aanscalefactor[$col]);
				$i++;
			}
		}
	}

	// 8*8 zigzag 배열로 변환후 return
	function getZigzag($arrData) {
		$zigzag	= array();
		$nZigZag = array(
			 0,	1,	5,	6, 14, 15, 27, 28,
			 2,	4,	7, 13, 16, 26, 29, 42, 
			 3,	8, 12, 17, 25, 30, 41, 43, 
			 9, 11, 18, 24, 31, 40, 44, 53, 
			10, 19, 23, 32, 39, 45, 52, 54, 
			20, 22, 33, 38, 46, 51, 55, 60, 
			21, 34, 37, 47, 50, 56, 59, 61, 
			35, 36, 48, 49, 57, 58, 62, 63
		);
		for ($i = 0; $i < 64; $i++) {
			//$this->loop_count++;

			$zigzag[intval($i/8)][$i%8] = $arrData[$nZigZag[$i]];
		}
		return $zigzag;
	}

	// debug
	function debug() {
		$startTime = array_sum(explode(' ',microtime()));

		// JPEG 파일구조 참고
		//echo '<a href="http://dryumbrella.blogspot.kr/2009/08/jpeg.html">JPEG file format 참고 URL</a><br />';
		//echo '<a href="http://sunshowers.tistory.com/69">JPEG file format 참고 URL</a><br />';
		//echo '<a href="http://minujang.egloos.com/241500">JPEG file format 참고 URL</a><br /><br />';
		//echo '<a href="https://docs.google.com/viewer?a=v&q=cache:uQpOu5R55W4J:heehiee.codns.com:9000/060611/0_%25C0%25FC%25C0%25DA%25C0%25DA%25B7%25E11_3(17G)/JPEG%2520%25BC%25D2%25BD%25BA/jpeg0610.pdf+JPEG+Dequantization+dc+code&hl=ko&gl=kr&pid=bl&srcid=ADGEEShhJV8NK8GC9PM87K-wXGxh0EKo0f5gc_WBqa26owdso14pHLfDxpYBkYMHbed7ziNksy2bfq86ltbFI3jW2lLiyg09FsS1bwgKR6CbNB10h75RNovF-Bf6U5ztZnpe6rHSI9v1&sig=AHIEtbSHleYfj8Au5tLFMwgdgaqZj99wfg">참고문서 GOOGLE READER URL</a><br />';
		//echo '<a href="https://docs.google.com/viewer?a=v&q=cache:n2RCiJQoiB4J:picaso.hannam.ac.kr/courses/Undergraduate/mediainfoproc/Lectnote/2002/chap09/%25EC%25A0%2595%25EC%25A7%2580%25EC%2598%2581%25EC%2583%2581%25EC%2595%2595%25EC%25B6%2595%25EB%25B6%2580%25ED%2598%25B8%25ED%2599%2594.pdf+JPEG+Dequantization+dc+code&hl=ko&gl=kr&pid=bl&srcid=ADGEEShWTsdE29a7-gPTAQdM6QjOleMruE-P2urqQ5uo-zQ5maAz1Fa-_5Mcg6yxzo42tvooGb0VyE5JwCfWecnTNHjg853o210AUE2BRlFhKpJ9oY-rHK1nSPFwnygWE00zhOAmTm4D&sig=AHIEtbQSQoMlND5SQhv4teiK6ugyfbbRIg">참고문서 GOOGLE READER URL</a><br />';
		//echo '<a href="http://www.stanford.edu/class/ee398a/handouts/lectures/08-JPEG.pdf">참고문서 PDF URL</a><br /><br />';

		// DEBUG 옵션 -> true	: MCU Data 값들을 화면에 출력
		//				 false : MCU Data Display (X), ECS Data Limits 50 Words, ...
		$debug = false;

		// Jpeg filename
		echo '<h1>'.$this->filename.'</h1>';
		echo '<a href="./'.$this->filename.'">&gt; <strong>이미지파일 원본 보기</strong></a><br />';

		echo '<a href="" onclick="document.getElementById(\'jpegDecoding\').style.display = \'block\'; return false;">&gt; <strong>헤더정보 자세히 보기</strong></a>';

		echo '<div id="jpegDecoding" style="display:none;">';
		// JFIF segment format
		echo '<h2>JFIF segment format</h2>';
		$soi = $this->getMarker('ffd8');
		echo 'SOI : '.$soi[0].'<br />';
		echo 'SOI : '.$soi[1].'<br /><br />';

		echo '<h2>APP0 Marker</h2>';
		$app0 = $this->getMarker('jfif::app0');
		echo 'APP0 : '.$app0[0].'<br />';
		echo 'APP0 : '.$app0[1].'<br /><br />';

		$length = $this->getMarker('jfif::length');
		echo 'Length : '.$length[0].'<br />';
		echo 'Length : '.$length[1].'<br /><br />';

		$identifier = $this->getMarker('jfif::identifier');
		echo 'Identifier : '.$identifier[0].'<br />';
		echo 'Identifier : '.$identifier[1].'<br /><br />';

		$version = $this->getMarker('jfif::version');
		echo 'Version : '.$version[0].'<br />';
		echo 'Version : '.$version[1].'<br /><br />';

		$density_units = $this->getMarker('jfif::density_units');
		echo 'Density Units : '.$density_units[0].'<br />';
		echo 'Density Units : '.$density_units[1].'<br /><br />';

		$x_density = $this->getMarker('jfif::x_density');
		echo 'X Density : '.$x_density[0].'<br />';
		echo 'X Density : '.$x_density[1].'<br /><br />';

		$y_density = $this->getMarker('jfif::y_density');
		echo 'Y Density : '.$y_density[0].'<br />';
		echo 'Y Density : '.$y_density[1].'<br /><br />';

		$thumbnail_width = $this->getMarker('jfif::thumbnail_width');
		echo 'Thumbnail Width : '.$thumbnail_width[0].'<br />';
		echo 'Thumbnail Width : '.$thumbnail_width[1].'<br /><br />';

		$thumbnail_height = $this->getMarker('jfif::thumbnail_height');
		echo 'Thumbnail Height : '.$thumbnail_height[0].'<br />';
		echo 'Thumbnail Height : '.$thumbnail_height[1].'<br /><br />';

		$thumbnail_data = $this->getMarker('jfif::thumbnail_data');
		echo 'Thumbnail Data : '.$thumbnail_data[0].'<br />';
		echo 'Thumbnail Data : '.$thumbnail_data[1].'<br /><br />';

		// DQT Marker
		// $dqtDataArr의 포인터변수로 사용
		$dqtArr = array();
		// 실제 DQT 정보가 저장되는 변수
		$dqtDataArr = array();
		for ($i = 0; $i < 4; $i++) {
			$dqt = $this->getMarker('dqt::dqt'.$i);

			if ($dqt[0] == 'ffdb') {
				echo '<h2>DQT Table'.$i.' Marker</h2>';
				if ($debug == true) {
					echo 'DQT : '.$dqt[0].'<br />';
					echo 'DQT : '.$dqt[1].'<br /><br />';
				}

				$lq = $this->getMarker('dqt::lq'.$i);
				if ($debug == true) {
					echo 'Lq : '.$lq[0].'<br />';
					echo 'Lq : '.$lq[1].'<br /><br />';
				}

				$pq = $this->getMarker('dqt::pq'.$i);
				if ($debug == true) {
					echo 'Pq : '.$pq[0].'<br />';
					echo 'Pq : '.$pq[1].'<br /><br />';
				}

				$tq = $this->getMarker('dqt::tq'.$i);
				if ($debug == true) {
					echo 'Tq : '.$tq[0].'<br />';
					echo 'Tq : '.$tq[1].'<br /><br />';
				}

				$table = $this->getMarker('dqt::table'.$i);
				if ($debug == true) {
					echo 'Table : '.$table[0].'<br />';
					echo 'Table : '.$table[1].'<br /><br />';
				}

				$dqtArr = &$dqtDataArr[$pq[0].$tq[0]];
				$eleLen = strlen($table[0]) / 2;
				for ($j = 0; $j < $eleLen; $j++) {
					$dqtArr[$j] = substr($table[0], $j*2, 2);
				}
				$dqtArr = $this->getZigzag($dqtArr);
				echo '<xmp>';
				for ($y = 0; $y < 8; $y++) {
					for ($x = 0; $x < 8; $x++) {
						echo hexdec($dqtArr[$y][$x])."\t";
					}
					echo "\n";
				}
				echo '</xmp>';
			} else {
				// endLoop
				break;
			}
		}
		/*
		// 표준 휘도용 양자화 테이블
		$dqtDataArr['00'] = array(
			array(16, 11, 10, 16,	24,	40,	51,	61),
			array(12, 12, 14, 19,	26,	58,	60,	66),
			array(14, 13, 16, 24,	40,	57,	69,	57),
			array(14, 17, 22, 29,	51,	87,	80,	62),
			array(18, 22, 37, 56,	68, 109, 103,	77),
			array(24, 36, 55, 64,	81, 104, 113,	92),
			array(49, 64, 78, 87, 103, 121, 120, 101),
			array(72, 92, 95, 98, 112, 100, 103,	99)
		);
		// 표준 색차용 양자화 테이블
		$dqtDataArr['01'] = array(
			array(17, 18, 24, 47, 99, 99, 99, 99),
			array(18, 21, 26, 66, 99, 99, 99, 99),
			array(24, 26, 56, 99, 99, 99, 99, 99),
			array(47, 66, 99, 99, 99, 99, 99, 99),
			array(99, 99, 99, 99, 99, 99, 99, 99),
			array(99, 99, 99, 99, 99, 99, 99, 99),
			array(99, 99, 99, 99, 99, 99, 99, 99),
			array(99, 99, 99, 99, 99, 99, 99, 99)
		);
		*/

		// SOF Marker
		$sofn = $this->getMarker('sofn::sofn');
		// n값 구하기
		$n = substr($sofn[0], 3, 1);
		echo '<h2>SOF'.$n.' Marker</h2>';
		echo 'SOF : '.$sofn[0].'<br />';
		echo 'SOF : '.$sofn[1].'<br /><br />';

		$sof_lf = $this->getMarker('sofn::lf');
		echo 'Lf : '.$sof_lf[0].'<br />';
		echo 'Lf : '.$sof_lf[1].'<br /><br />';

		$sof_p = $this->getMarker('sofn::p');
		echo 'P : '.$sof_p[0].'<br />';
		echo 'P : '.$sof_p[1].'<br /><br />';

		$sof_y = $this->getMarker('sofn::y');
		echo 'Y : '.$sof_y[0].'<br />';
		echo 'Y : '.$sof_y[1].'<br /><br />';

		$sof_x = $this->getMarker('sofn::x');
		echo 'X : '.$sof_x[0].'<br />';
		echo 'X : '.$sof_x[1].'<br /><br />';

		$sof_nf = $this->getMarker('sofn::nf');
		echo 'Nf : '.$sof_nf[0].'<br />';
		echo 'Nf : '.$sof_nf[1].'<br /><br />';

		$sampling_h = array('Y'=>0,'Cb'=>0,'Cr'=>0);
		$sampling_v = array('Y'=>0,'Cb'=>0,'Cr'=>0);
		$sampling_qt = array('Y'=>0,'Cb'=>0,'Cr'=>0);

		$sofNfLen = hexdec($sof_nf[0]);
		for ($i = 0; $i < $sofNfLen; $i++) {
			$sof_c = $this->getMarker('sofn::c'.$i);
			echo 'C'.$i.' : '.$sof_c[0].'<br />';
			echo 'C'.$i.' : '.$sof_c[1].'<br /><br />';

			$sof_h = $this->getMarker('sofn::h'.$i);
			echo 'H'.$i.' : '.$sof_h[0].'<br />';
			echo 'H'.$i.' : '.$sof_h[1].'<br /><br />';

			$sof_v = $this->getMarker('sofn::v'.$i);
			echo 'V'.$i.' : '.$sof_v[0].'<br />';
			echo 'V'.$i.' : '.$sof_v[1].'<br /><br />';

			$sof_qt = $this->getMarker('sofn::qt'.$i);
			echo 'QT'.$i.' : '.$sof_qt[0].'<br />';
			echo 'QT'.$i.' : '.$sof_qt[1].'<br /><br />';

			// 셈플링 정보 저장
			switch($i) {
				case 0:
					$sampling_h['Y'] = $sof_h[0];
					$sampling_v['Y'] = $sof_v[0];
					$sampling_qt['Y'] = $sof_qt[0];
					break;
				case 1:
					$sampling_h['Cb'] = $sof_h[0];
					$sampling_v['Cb'] = $sof_v[0];
					$sampling_qt['Cb'] = $sof_qt[0];
					break;
				case 2:
					$sampling_h['Cr'] = $sof_h[0];
					$sampling_v['Cr'] = $sof_v[0];
					$sampling_qt['Cr'] = $sof_qt[0];
					break;
			}
		}

		// DHT Marker
		for ($i = 0; $i < 4; $i++) {
			if ($debug == true) echo '<h2>DHT'.$i.' Marker</h2>';
			$dht = $this->getMarker('dht::dht'.$i);
			if ($debug == true) {
				echo 'DHT'.$i.' : '.$dht[0].'<br />';
				echo 'DHT'.$i.' : '.$dht[1].'<br /><br />';
			}

			$dht_dl = $this->getMarker('dht::dl'.$i);
			if ($debug == true) {
				echo 'DL'.$i.' : '.$dht_dl[0].'<br />';
				echo 'DL'.$i.' : '.$dht_dl[1].'<br /><br />';
			}

			$dht_tc = $this->getMarker('dht::tc'.$i);
			if ($debug == true) {
				echo 'TC'.$i.' : '.$dht_tc[0].'<br />';
				echo 'TC'.$i.' : '.$dht_tc[1].'<br /><br />';
			}

			$dht_ti = $this->getMarker('dht::ti'.$i);
			if ($debug == true) {
				echo 'TI'.$i.' : '.$dht_ti[0].'<br />';
				echo 'TI'.$i.' : '.$dht_ti[1].'<br /><br />';
			}

			$dht_cl = $this->getMarker('dht::cl'.$i);
			if ($debug == true) {
				echo 'CL'.$i.' : '.$dht_cl[0].'<br />';
				echo 'CL'.$i.' : '.$dht_cl[1].'<br /><br />';
			}

			$dht_sb = $this->getMarker('dht::sb'.$i);
			if ($debug == true) {
				echo 'SB'.$i.' : '.$dht_sb[0].'<br />';
				echo 'SB'.$i.' : '.$dht_sb[1].'<br /><br />';
			}

			// Huffman Decoding Text
			if ($debug == true) echo '<br />';
			$this->decHuffman($dht_cl[0], $dht_sb[0]);
		}

		// SOS Marker
		echo '<h2>SOS Marker</h2>';
		$sos = $this->getMarker('sos::sos');
		echo 'SOS : '.$sos[0].'<br />';
		echo 'SOS : '.$sos[1].'<br /><br />';

		$sos_ls = $this->getMarker('sos::ls');
		echo 'LS : '.$sos_ls[0].'<br />';
		echo 'LS : '.$sos_ls[1].'<br /><br />';

		$sos_ns = $this->getMarker('sos::ns');
		echo 'NS : '.$sos_ns[0].'<br />';
		echo 'NS : '.$sos_ns[1].'<br /><br />';

		$huffmanYCbCr = array('Y'=>0, 'Cb'=>0, 'Cr'=>0);
		$n = hexdec($sos_ns[0]);
		for ($i = 0; $i < $n; $i++) {
			$sos_cs = $this->getMarker('sos::cs'.$i);
			echo 'CS'.$i.' : '.$sos_cs[0].'<br />';
			echo 'CS'.$i.' : '.$sos_cs[1].'<br /><br />';

			$sos_td = $this->getMarker('sos::td'.$i);
			echo 'TD'.$i.' : '.$sos_td[0].'<br />';
			echo 'TD'.$i.' : '.$sos_td[1].'<br /><br />';

			$sos_ta = $this->getMarker('sos::ta'.$i);
			echo 'TA'.$i.' : '.$sos_ta[0].'<br />';
			echo 'TA'.$i.' : '.$sos_ta[1].'<br /><br />';

			switch ($i) {
				case 0:
					$huffmanYCbCr['Y'] = $sos_td[0].$sos_ta[0];
					break;
				case 1:
					$huffmanYCbCr['Cb'] = $sos_td[0].$sos_ta[0];
					break;
				case 2:
					$huffmanYCbCr['Cr'] = $sos_td[0].$sos_ta[0];
					break;
			}
		}

		$sos_ss = $this->getMarker('sos::ss');
		echo 'SS : '.$sos_ss[0].'<br />';
		echo 'SS : '.$sos_ss[1].'<br /><br />';

		$sos_se = $this->getMarker('sos::se');
		echo 'SE : '.$sos_se[0].'<br />';
		echo 'SE : '.$sos_se[1].'<br /><br />';

		$sos_ah = $this->getMarker('sos::ah');
		echo 'AH : '.$sos_ah[0].'<br />';
		echo 'AH : '.$sos_ah[1].'<br /><br />';

		$sos_al = $this->getMarker('sos::al');
		echo 'AL : '.$sos_al[0].'<br />';
		echo 'AL : '.$sos_al[1].'<br /><br />';

		// ECS (ScanData) Entropy Coded Signal Decoding
		echo '<h2>ECS (Entropy Coded Signal) Decoding</h2>';
		$scan_data = $this->getMarker('scan::data');
		$dScan_data = ($debug == true)? $scan_data[0]: substr($scan_data[0], 0, 50).'<b>...</b>';

		echo 'ECS : '.$dScan_data.'<br />';
		echo 'ECS : '.$scan_data[1].'<br /><br />';
		// 10 -> 2진수
		$seconds_data = '';
		$scan_len = strlen($scan_data[0]);
		for ($i = 0; $i < $scan_len; $i++) {
			if ($i > 0)
				$seconds_data .= sprintf("%04b", hexdec(substr($scan_data[0], $i, 1)));
			else
				$seconds_data .= sprintf("%b", hexdec(substr($scan_data[0], $i, 1)));
		}
		$dSec_data = ($debug == true)? $seconds_data: substr($seconds_data, 0, 50).'<b>...</b>';
		if ($debug == true) echo 'ECS SEC : '.$dSec_data.'<br /><br />';

		// EOI Marker
		echo '<h2>EOI Marker</h2>';
		$eoi = $this->getMarker('ffd9');
		echo 'EOI : '.$eoi[0].'<br />';
		echo 'EOI : '.$eoi[1].'<br /><br />';
		echo '</div>';

		echo '<h2>JPEG Decoding (Entropy coded Signal Decoding)</h2>';
		echo '이미지 셈플링 비트 : '.hexdec($sof_p[0]).'bit<br />';
		echo '가로 셈플링 주기 : '.$sampling_h['Y'].':'.$sampling_h['Cb'].':'.$sampling_h['Cr'].'<br />';
		echo '세로 셈플링 주기 : '.$sampling_v['Y'].':'.$sampling_v['Cb'].':'.$sampling_v['Cr'].'<br /><br />';
		$maxSamplingWidth = 0;
		$maxSamplingHeight = 0;
		foreach ($sampling_h as $k=>$v) {
			if ($maxSamplingWidth < $sampling_h[$k]) $maxSamplingWidth = $sampling_h[$k];
			if ($maxSamplingHeight < $sampling_v[$k]) $maxSamplingHeight = $sampling_v[$k];
		}

		// Entropy Decoding (VLD) -> Dequantization

		$width = hexdec($sof_x[0]);
		$height = hexdec($sof_y[0]);
		$mcuWidth = $maxSamplingWidth * 8;
		$mcuHeight = $maxSamplingHeight * 8;
		$mcuHCount = ceil($width / $mcuWidth);
		$mcuVCount = ceil($height / $mcuHeight);
		//$mcuHCount = ceil((hexdec($sof_x[0]) + $mcuWidth - 1) / $mcuWidth);
		//$mcuVCount = ceil((hexdec($sof_y[0]) + $mcuHeight - 1) / $mcuHeight);
		$totalMCUCount = $mcuHCount * $mcuVCount;

		// MCU 개수 만큼 MCU Data를 구함
		if ($debug == true) echo '<div id="hidden" style="display:none;">';

		if ($debug == true) echo '<xmp>';
		$rgbPX = array();
		$rgbArr = array();
		$tmp = array('Y'=>0, 'Cb'=>0, 'Cr'=>0);
		$decValue = array('Y'=>0, 'Cb'=>0, 'Cr'=>0);
		$sampling_m = $maxSamplingWidth*$maxSamplingHeight;
		// idct init 초기화
		$this->idct_init();
		$attRGB = array();
		$attRGB['r'] = 0;
		$arrRGB['rc'] = 0;
		$attRGB['g'] = 0;
		$arrRGB['gc'] = 0;
		$attRGB['b'] = 0;
		$arrRGB['bc'] = 0;
		for ($n = 0; $n < $totalMCUCount; $n++) {
			$data = array();
			$pixel = array();
			$arrMCU = $this->getMCU($seconds_data, $sampling_h, $sampling_v, $huffmanYCbCr);

			// Y, Cb, Cr -> 3
			// 여기서 Cb, Cr 값들을 전부 `0` 으로 설정할 경우 흑백사진이 출력
			$nTitle = array('Y', 'Cb', 'Cr');
			for ($kk = 0; $kk < 3; $kk++) {
				if ($debug == true) echo '### MCU'.$n.' '.$nTitle[$kk]." ###\n";
				$mcuDataArr = $arrMCU[$nTitle[$kk]];
				$mcuDataArrCount = count($mcuDataArr);
				for ($k = 0; $k < $mcuDataArrCount; $k++) {
					$v = $mcuDataArr[$k];
					// DC값 수정
					$v[0][0] = $v[0][0] + $decValue[$nTitle[$kk]];
					$decValue[$nTitle[$kk]] = $v[0][0];
					// 양자화 테이블 설정
					$this->setQtable($dqtDataArr[$sampling_qt[$nTitle[$kk]]]);
					// 역양자화 -> IDCT실행
					$this->idct($v, $pixel[$nTitle[$kk]][$k]);

					if ($debug == true) {
						for ($i = 0; $i < 8; $i++) {
							for ($j = 0; $j < 8; $j++) {
								echo $pixel[$nTitle[$kk]][$k][$i][$j]."\t";
							}
							echo "\n";
						}
						echo "\n";
					}
				}
			}

			//echo "### Decoding RGB Data ###\n";
			// 업 셈플링 YCbCr => RGB 변환
			$pixelDataArr = $pixel['Y'];
			foreach ($pixelDataArr as $k=>$v) {
				for ($i = 0; $i < 8; $i++) {
					for ($j = 0; $j < 8; $j++) {
						$this->loop_count++;

						if (!$pixel['Y'][$k] || !$pixel['Cb'][$k] || !$pixel['Cr'][$k]) {
							if (!$pixel['Y'][$k])	$pixel['Y'][$k] = $pixel['Y'][0];
							if (!$pixel['Cb'][$k]) $pixel['Cb'][$k] = $pixel['Cb'][0];
							if (!$pixel['Cr'][$k]) $pixel['Cr'][$k] = $pixel['Cr'][0];
						}

						$y	= $pixel['Y'][$k][$i][$j];
						$cb = $pixel['Cb'][$k][$i][$j];
						$cr = $pixel['Cr'][$k][$i][$j];

						// Up-Sampling
						// YUV -> RGB Convert
						//$r = $this->intRGB($y + (22970 * ($cr - 128) >> 14));
						//$g = $this->intRGB($y - (5638 * ($cb - 128) >> 14) - (11700 * ($cr - 128) >> 14));
						//$b = $this->intRGB($y + (29032 * ($cb - 128) >> 14));

						//$r = $this->intRGB(1.164 * ($y - 16) + 1.596 * ($cr - 128));
						//$g = $this->intRGB(1.164 * ($y - 16) - 0.813 * ($cr - 128) - 0.391 * ($cb - 128));
						//$b = $this->intRGB(1.164 * ($y - 16) + 2.018 * ($cb - 128));

						$r = $this->intRGB($y + 1.402 * ($cr - 128));
						$g = $this->intRGB($y - 0.34414 * ($cb - 128) - 0.71414 * ($cr - 128));
						$b = $this->intRGB($y + 1.774 * ($cb - 128));

						// 색상 평균값 설정
						$attRGB['y'] += $y;
						$attRGB['yc']++;
						$attRGB['cb'] += $cb;
						$attRGB['cbc']++;
						$attRGB['cr'] += $cr;
						$attRGB['crc']++;

						// 16진수의 RGB 색상값으로 변환
						$color = $this->dec16($r).$this->dec16($g).$this->dec16($b);
						$rgbArr[($sampling_m*$n)+$k][$i][$j] = $color;

						// Pixel 값 출력
						//echo $color.' ';
					}
					//echo "\n";
				}
			}
		}
		if ($debug == true)
			echo '</xmp></div><a href="" onclick="document.getElementById(\'hidden\').style.display = \'\'; return false;">more</a>';

		// Pixel Debug
		$xidx = 0;
		$yidx = 0;
		$mcuCount = 0;
		$decoding = array();
		$sampling_m = $maxSamplingWidth*$maxSamplingHeight;
		$tMCUCount = $totalMCUCount*$sampling_m;
		for ($i = 0; $i < $tMCUCount; $i++) {
			$this->loop_count++;

			$decoding[$yidx][$xidx] = $rgbArr[$i];
			// 이미지 그리기
			if ($sampling_m == 4) {
				switch($i%4) {
					case 0: $xidx++; break;
					case 1: $xidx--; $yidx++; break;
					case 2: $xidx++; break;
					case 3:
						$mcuCount++;
						if (($mcuCount * 2) % ($mcuHCount * 2) == 0) {
							$xidx = 0;
							$yidx++;
						} else {	
							$xidx++;
							$yidx--;
						}
						break;
				}
			} else {
				$xidx++;
				if ($mcuHCount == $xidx) { 
					$xidx = 0;
					$yidx++;
				}
			}
		}

		// 이미지 화질의 경우 양자화 테이블과 관련이 있는것 같다
		// 2013-04-14
		//
		// visual c - jpeg decoder source
		// https://code.google.com/p/jpegant/source/browse/src/decoder/main.cpp?name=master
		echo '<xmp>';
		echo "### Display Image ###\n";
		echo '</xmp>';

		$px_id = 0;
		$h = $mcuHCount * $maxSamplingWidth;
		$v = $mcuVCount * $maxSamplingHeight;
		$colorArray = array();

		echo '<div style="float:left; overflow:hidden; width:'.$width.'px; height:'.$height.'px;">';
		for ($i = 0; $i < $v; $i++) {
			echo '<div style="clear:both; font-size:1px; width:'.($width*1.5).'px;">';
			for ($j = 0; $j < $h; $j++) {
				echo '<div class="px_cc" style="float:left; position:relative; top:0; left:0;">';
				for ($y = 0; $y < 8; $y++) {
					echo '<div style="float:left; clear:both;">';
					for ($x = 0; $x < 8; $x++) {
						$this->loop_count++;
						// 해당 Pixel의 고유 순서번호 값
						$px_id = $i*$h*8*8+$y*$h*8+$j*8+$x;

						// Debug 코드
						// 해당 이미지의 크기에 해당하는 부분만 출력
						if ($px_id/($h*8) < $height && $px_id%($h*8) < $width)
						{
							echo '<div id="px_id_'.$px_id.'" class="px" style="background:#'.$decoding[$i][$j][$y][$x].';"><!--//--></div>';
						//	echo '<div class="px" style="background:#'.$decoding[$i][$j][$y][$x].';"><!--//--></div>';
							$colorArray[$decoding[$i][$j][$y][$x]] .= $px_id.',';
						}
					}
					echo '</div>';
				}
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';

		// 원본 이미지 로딩
		echo '<div style="float:left; margin-left:10px;">';
		echo '<img src="./'.$this->filename.'" alt="" style="clear:both;" />';
		echo '</div>';
		?>
		<script type="text/javascript">
		var vv = <?php echo $v; ?>;
		var hh = <?php echo $h; ?>;
		var w = <?php echo $width; ?>;
		var h = <?php echo $height; ?>;
		var px = $('.px_cc');

		// 렌덤하게 재 배치
		var pxArr = new Array();
		var floor = Math.floor;
		var random = Math.random;
		for (var i = 0; i < px.length; i++) {
			pxArr[i] = $(px[i]);
		}
		
		function random_position_init() {
			var randX = 0;
			var randY = 0;
			var sec = 0;
			var pxObject = null;
			// 렌덤하게 재 배치
			for (var i = 0; i < px.length; i++) {
				pxObject = pxArr[i];

				randX = floor(random() * w);
				randY = floor(random() * h);
				sec = floor(random() * 5) + 2;
				pxObject.animate({'top' : randX, 'left' : randY}, sec * 1000);
			}
		}
		function random_position_ani() {
			var randX = 0;
			var randY = 0;
			var sec = 0;
			var pxObject = null;
			// 렌덤하게 재 배치
			for (var i = 0; i < px.length; i++) {
				pxObject = pxArr[i];
				sec = floor(random() * 5) + 2;
				pxObject.animate({'top' : 0, 'left' : 0}, sec * 1000);
			}
		}
		function random_alpha_fade_out() {
			var sec = 0;
			for (var i = 0; i < px.length; i++) {
				sec = floor(random() * 5) + 2;
				pxArr[i].fadeTo(sec * 1000, 0);
			}
		}
		function random_alpha_fade_in() {
			var sec = 0;
			for (var i = 0; i < px.length; i++) {
				sec = floor(random() * 5) + 2;
				pxArr[i].fadeTo(sec * 1000, 1);
			}
		}
		</script>
		<?php
		$y = round($attRGB['y']/($attRGB['yc']+1));
		$cb = round($attRGB['cb']/($attRGB['cbc']+1));
		$cr = round($attRGB['cr']/($attRGB['crc']+1));
		$r = $this->intRGB($y + 1.402 * ($cr - 128));
		$g = $this->intRGB($y - 0.34414 * ($cb - 128) - 0.71414 * ($cr - 128));
		$b = $this->intRGB($y + 1.774 * ($cb - 128));
		$color = $this->dec16($r).$this->dec16($g).$this->dec16($b);

		echo '<div style="clear:both; padding:0;">';
		echo '<div style="float:left;">이미지 전체 색상평균값 : </div>';
		echo '<div class="px" style="margin-top:3px; margin-left:3px; width:10px; height:10px; background:#'.$color.';"><!--//--></div> #'.$color.'';
		echo '</div>';

		echo '<div>이미지에 사용된 모든 색상값 : </div>';
		echo '<div style="clear:both; width:500px; height:100px; border:2px solid #000; overflow:auto; padding:5px 3px 5px 5px; margin-bottom:30px;">';
		ksort($colorArray);
		foreach($colorArray as $color => $pxids) {
			echo '<div class="px" title="#'.$color.'" style="margin-top:3px; margin-left:3px; width:10px; height:10px; background:#'.$color.';"><!--//--></div>';
		}
		echo '</div>';
		?>
		<input type="button" value="fadeOut 효과" style="font-size:12px; padding:5px; font-weight:bold; color:#f00;" onclick="random_alpha_fade_out();" />
		<input type="button" value="fadeIn 효과" style="font-size:12px; padding:5px; font-weight:bold; color:#f00;" onclick="random_alpha_fade_in();" />
		<input type="button" value="이미지 흩뿌리기" style="font-size:12px; padding:5px; font-weight:bold; color:#f00;" onclick="random_position_init();" />
		<input type="button" value="이미지 움직임 효과" style="font-size:12px; padding:5px; font-weight:bold; color:#f00;" onclick="random_position_ani();" />
		<?php
		// 이미지 전체 Hex Data 보기
		//echo '<div id="hidden" style="display:none;">'.implode($this->dataArr, ' ').'</div><a href="" onclick="document.getElementById(\'hidden\').style.display = \'\'; return false;">more</a>';

		$endTime = array_sum(explode(' ',microtime()));
		$this->php_time = $endTime - $startTime;
	}
}

$jpg = new fmJPEG($filename);
$jpg->debug();

echo '<div style="clear:both;">코드 실행속도 : <b>'.$jpg->php_time.'</b>초</div>';
echo '<div style="clear:both;">코드 루프 실행횟수 : <b>'.$jpg->loop_count.'</b>회</div>';

echo '<div style="clear:both; margin-top:15px;">IDCT 루프 실행횟수 : <b>'.$jpg->idct_loop_count.'</b>회</div>';
echo '<div style="clear:both;">MCU 루프 실행횟수 : <b>'.$jpg->mcu_loop_count.'</b>회</div>';
?>

</body>
</html>
<?php
ob_end_flush();
?>
