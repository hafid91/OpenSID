<?php

class Web_Dokumen_Model extends CI_Model{

	function __construct(){
		parent::__construct();
	}

	function autocomplete(){
		$sql = "SELECT satuan FROM dokumen WHERE id_pend = 0
					UNION SELECT nama FROM dokumen WHERE id_pend = 0";
		$query = $this->db->query($sql);
		$data  = $query->result_array();

		$i=0;
		$outp='';
		while($i<count($data)){
			$outp .= ",'" .$data[$i]['satuan']. "'";
			$i++;
		}
		$outp = strtolower(substr($outp, 1));
		$outp = '[' .$outp. ']';
		return $outp;
	}

	function search_sql(){
		if(isset($_SESSION['cari'])){
		$cari = $_SESSION['cari'];
			$kw = $this->db->escape_like_str($cari);
			$kw = '%' .$kw. '%';
			$search_sql= " AND (satuan LIKE '$kw' OR nama LIKE '$kw')";
			return $search_sql;
			}
		}

	function filter_sql(){
		if(isset($_SESSION['filter'])){
			$kf = $_SESSION['filter'];
			$filter_sql= " AND enabled = $kf";
		return $filter_sql;
		}
	}

	function paging($p=1,$o=0){

		$sql      = "SELECT COUNT(id) AS id FROM dokumen WHERE id_pend = 0";
		$sql     .= $this->search_sql();
		$query    = $this->db->query($sql);
		$row      = $query->row_array();
		$jml_data = $row['id'];

		$this->load->library('paging');
		$cfg['page']     = $p;
		$cfg['per_page'] = $_SESSION['per_page'];
		$cfg['num_rows'] = $jml_data;
		$this->paging->init($cfg);

		return $this->paging;
	}

	function list_data($o=0,$offset=0,$limit=500){

		switch($o){
			case 1: $order_sql = ' ORDER BY nama'; break;
			case 2: $order_sql = ' ORDER BY nama DESC'; break;
			case 3: $order_sql = ' ORDER BY enabled'; break;
			case 4: $order_sql = ' ORDER BY enabled DESC'; break;
			case 5: $order_sql = ' ORDER BY tgl_upload'; break;
			case 6: $order_sql = ' ORDER BY tgl_upload DESC'; break;
			default:$order_sql = ' ORDER BY id';
		}

		$paging_sql = ' LIMIT ' .$offset. ',' .$limit;

		$sql   = "SELECT * FROM dokumen WHERE id_pend = 0";

		$sql .= $this->search_sql();
		$sql .= $this->filter_sql();
		$sql .= $order_sql;
		$sql .= $paging_sql;

		$query = $this->db->query($sql);
		$data=$query->result_array();

		$i=0;
		$j=$offset;
		while($i<count($data)){
			$data[$i]['no']=$j+1;

			if($data[$i]['enabled']==1)
				$data[$i]['aktif']="Yes";
			else
				$data[$i]['aktif']="No";

			$i++;
			$j++;
		}
		return $data;
	}

	function semua_mime_type(){
	  $semua_mime_type = array_merge(unserialize(MIME_TYPE_DOKUMEN), unserialize(MIME_TYPE_GAMBAR), unserialize(MIME_TYPE_ARSIP));
	  $semua_mime_type = array_diff($semua_mime_type, array('application/octet-stream'));
	  return $semua_mime_type;
	}

	function semua_ext(){
	  $semua_ext = array_merge(unserialize(EXT_DOKUMEN), unserialize(EXT_GAMBAR), unserialize(EXT_ARSIP));
	  return $semua_ext;
	}

	function upload_dokumen(&$data, $file_lama=""){
		unset($data['old_file']);
		if(empty($_FILES['satuan']['tmp_name'])){
			return false;
		}

		$_SESSION['error_msg'] = "";
		$_SESSION['success'] = 1;
	  $lokasi_file = $_FILES['satuan']['tmp_name'];
		if(empty($lokasi_file)){
			$_SESSION['success']=-1;
			return false;
		}
	  if (function_exists('finfo_open')) {
	    $finfo = finfo_open(FILEINFO_MIME_TYPE);
	    $tipe_file = finfo_file($finfo, $lokasi_file);
	  } else
		  $tipe_file = $_FILES['satuan']['type'];
	  $nama_file   = $_FILES['satuan']['name'];
	  $nama_file   = str_replace(' ', '-', $nama_file); 	 // normalkan nama file
	  $ext = get_extension($nama_file);

		if(!in_array($tipe_file, $this->semua_mime_type()) OR !in_array($ext, $this->semua_ext())){
			$_SESSION['error_msg'].= " -> Jenis file salah: " . $tipe_file . " " . $ext;
			$_SESSION['success']=-1;
			return false;
		} elseif(isPHP($lokasi_file, $nama_file)){
			$_SESSION['error_msg'].= " -> File berisi script ";
			$_SESSION['success']=-1;
			return false;
		}

		if (!empty($data['id_pend']))
			$nama_file = $data['id_pend']."_".$data['nama']."_".generator(6)."_".$nama_file;
		else
			$nama_file = $data['nama']."_".generator(6)."_".$nama_file;
		$nama_file = urlencode($nama_file);
		UploadDocument($nama_file, $file_lama);
		$data['satuan'] = $nama_file;
		return true;
	}

	function insert(){
		$data = $_POST;
		if ($this->upload_dokumen($data))
			$this->db->insert('dokumen',$data);
	}

	function update($id=0){
	  $data = $_POST;
		if (!$this->upload_dokumen($data, $data['old_file']))
			unset($data['satuan']);
		$this->db->where('id',$id)->update('dokumen',$data);;
	}

	function delete($id=''){
		$old_dokumen = $this->db->select('satuan')->where('id',$id)->get('dokumen')->row()->satuan;
		$outp = $this->db->where('id',$id)->delete('dokumen');
		if($outp)
			unlink(LOKASI_DOKUMEN . $old_dokumen);
		else $_SESSION['success']=-1;
	}

	function delete_all(){
		$id_cb = $_POST['id_cb'];
		if(count($id_cb)){
			foreach($id_cb as $id){
				$this->delete($id);
			}
		}
		else $_SESSION['success']=-1;
	}

	function dokumen_lock($id='',$val=0){

		$sql  = "UPDATE dokumen SET enabled=? WHERE id=?";
		$outp = $this->db->query($sql, array($val,$id));

		if($outp) $_SESSION['success']=1;
			else $_SESSION['success']=-1;
	}

	function get_dokumen($id=0){
		$sql   = "SELECT * FROM dokumen WHERE id=?";
		$query = $this->db->query($sql,$id);
		$data  = $query->row_array();
		return $data;
	}

	function dokumen_show(){
		$sql   = "SELECT * FROM dokumen WHERE enabled=?";
		$query = $this->db->query($sql,1);
		$data  = $query->result_array();
		return $data;
	}
}
?>
