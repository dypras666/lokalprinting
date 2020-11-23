<?php
defined('BASEPATH') or exit('No direct script access allowed');
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class Welcome extends CI_Controller
{

    /**
     * Index Page for this controller.
     *
     * Maps to the following URL
     * 		http://example.com/index.php/welcome
     *	- or -
     * 		http://example.com/index.php/welcome/index
     *	- or -
     * Since this controller is set as the default controller in
     * config/routes.php, it's displayed at http://example.com/
     *
     * So any other public methods not prefixed with an underscore will
     * map to /index.php/welcome/<method_name>
     * @see https://codeigniter.com/user_guide/general/urls.html
     */
    public function index()
    {
        $this->load->view('welcome_message');
    }

    public function nota($kode)
    {
        header('Content-Type: application/json');
        $uri = "https://sim.saktiputra.com/api/plastik/GetNota/".$kode;

        $ci = curl_init();
        curl_setopt($ci, CURLOPT_URL, $uri);
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, 1);
        $fetch =   curl_exec($ci);
        // echo $fetch;
        $data = json_decode($fetch);
        // var_dump($data);
        $connector = new WindowsPrintConnector('ZJ-58');
        $printer = new Printer($connector);
        function buatBaris4Kolom($kolom1, $kolom2, $kolom3, $kolom4)
        {
            // Mengatur lebar setiap kolom (dalam satuan karakter)
            $lebar_kolom_1 = 12;
            $lebar_kolom_2 = 8;
            $lebar_kolom_3 = 8;
            $lebar_kolom_4 = 9;
 
            // Melakukan wordwrap(), jadi jika karakter teks melebihi lebar kolom, ditambahkan \n
            $kolom1 = wordwrap($kolom1, $lebar_kolom_1, "\n", true);
            $kolom2 = wordwrap($kolom2, $lebar_kolom_2, "\n", true);
            $kolom3 = wordwrap($kolom3, $lebar_kolom_3, "\n", true);
            $kolom4 = wordwrap($kolom4, $lebar_kolom_4, "\n", true);
 
            // Merubah hasil wordwrap menjadi array, kolom yang memiliki 2 index array berarti memiliki 2 baris (kena wordwrap)
            $kolom1Array = explode("\n", $kolom1);
            $kolom2Array = explode("\n", $kolom2);
            $kolom3Array = explode("\n", $kolom3);
            $kolom4Array = explode("\n", $kolom4);
 
            // Mengambil jumlah baris terbanyak dari kolom-kolom untuk dijadikan titik akhir perulangan
            $jmlBarisTerbanyak = max(count($kolom1Array), count($kolom2Array), count($kolom3Array), count($kolom4Array));
 
            // Mendeklarasikan variabel untuk menampung kolom yang sudah di edit
            $hasilBaris = array();
 
            // Melakukan perulangan setiap baris (yang dibentuk wordwrap), untuk menggabungkan setiap kolom menjadi 1 baris
            for ($i = 0; $i < $jmlBarisTerbanyak; $i++) {
 
                // memberikan spasi di setiap cell berdasarkan lebar kolom yang ditentukan,
                $hasilKolom1 = str_pad((isset($kolom1Array[$i]) ? $kolom1Array[$i] : ""), $lebar_kolom_1, " ");
                $hasilKolom2 = str_pad((isset($kolom2Array[$i]) ? $kolom2Array[$i] : ""), $lebar_kolom_2, " ");
 
                // memberikan rata kanan pada kolom 3 dan 4 karena akan kita gunakan untuk harga dan total harga
                $hasilKolom3 = str_pad((isset($kolom3Array[$i]) ? $kolom3Array[$i] : ""), $lebar_kolom_3, " ", STR_PAD_LEFT);
                $hasilKolom4 = str_pad((isset($kolom4Array[$i]) ? $kolom4Array[$i] : ""), $lebar_kolom_4, " ", STR_PAD_LEFT);
 
                // Menggabungkan kolom tersebut menjadi 1 baris dan ditampung ke variabel hasil (ada 1 spasi disetiap kolom)
                $hasilBaris[] = $hasilKolom1 . " " . $hasilKolom2 . " " . $hasilKolom3 . " " . $hasilKolom4;
            }
 
            // Hasil yang berupa array, disatukan kembali menjadi string dan tambahkan \n disetiap barisnya.
            return implode($hasilBaris, "\n") . "\n";
        }
        try {
            $printer->initialize();
            $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT); // Setting teks menjadi lebih besar
                $printer->setJustification(Printer::JUSTIFY_CENTER); // Setting teks menjadi rata tengah
                $printer->text($data->data[0]->cabang->nama."\n");
            $printer->text($data->data[0]->cabang->alamat."\n");
            $printer->text("\n");
             

            // Data transaksi
            $printer->initialize();
            $printer->text("kasir: ".$data->data[0]->kasir."\n");
            $printer->text("Customer: ".$data->data[0]->customer."\n");
            $printer->text("NO NOTA: ".$data->data[0]->kode." \n");


            // Membuat tabel
                $printer->initialize(); // Reset bentuk/jenis teks
                $printer->text("----------------------------------------\n");
            $printer->text(buatBaris4Kolom("Barang", "qty", "Harga", "Subtotal"));
            $printer->text("----------------------------------------\n");
            foreach ($data->data[0]->produk as $v) {
                $printer->text(buatBaris4Kolom($v->produk, $v->qty. ' '.$v->satuan, $v->harga, $v->harga_asli ."\n Diskon : ". $v->diskon));
            }
            $printer->text("----------------------------------------\n");
            $printer->text(buatBaris4Kolom('', '', "Total", $data->data[0]->total->harga_asli));
            $printer->text(buatBaris4Kolom('', '', "Diskon", $data->data[0]->total->diskon));
            $printer->text(buatBaris4Kolom('', '', "S.Total", $data->data[0]->total->harga));
            $printer->text(buatBaris4Kolom('', '', "Bayar", $data->data[0]->total->bayar));
            $printer->text(buatBaris4Kolom('', '', "Kembali", $data->data[0]->total->kembali));
            $printer->text("\n");



            // Pesan penutup
            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Terima kasih telah berbelanja\n");
            $printer->text($data->data[0]->tanggal."\n");
            $printer->feed(2);
            $printer -> cut();
            $printer -> close();
        } finally {
            $printer -> close();
        }
    }

    public function nota_kecil($kode)
    {
        header('Content-Type: application/json');
        $uri = "https://sim.saktiputra.com/api/plastik/GetNota/".$kode;

        $ci = curl_init();
        curl_setopt($ci, CURLOPT_URL, $uri);
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, 1);
        $fetch =   curl_exec($ci);
        // echo $fetch;
        $data = json_decode($fetch);
        // var_dump($data);
        $connector = new WindowsPrintConnector('ZJ-58');
        $printer = new Printer($connector);
        try {
            $printer->initialize();
            $printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT); // Setting teks menjadi lebih besar
                $printer->setJustification(Printer::JUSTIFY_CENTER); // Setting teks menjadi rata tengah
                $printer->text($data->data[0]->cabang->nama."\n");
            $printer->text($data->data[0]->cabang->alamat."\n");
            $printer->text("\n");
             

            // Data transaksi
            $printer->initialize();
            $printer->text("kasir: ".$data->data[0]->kasir."\n");
            $printer->text("Customer: ".$data->data[0]->customer."\n");
            $printer->text("NO NOTA: ".$data->data[0]->kode." \n");


            // Membuat tabel
                $printer->initialize(); // Reset bentuk/jenis teks
                $printer->text("----------------------------------------\n");
            $printer->text("PRODUK");
            $printer->text("----------------------------------------\n");
            foreach ($data->data[0]->produk as $v) {
                $printer->text($v->produk . "\n". $v->qty . "" .$v->satuan ." X ". $v->harga . "\n ".  $v->harga_asli ."\n Diskon : ". $v->diskon);
            }
            $printer->text("----------------------------------------\n");
            $printer->text("Total: ".  $data->data[0]->total->harga_asli);
            $printer->text("Diskon: ". $data->data[0]->total->diskon);
            $printer->text("S.Total: ". $data->data[0]->total->harga);
            $printer->text("Bayar: ". $data->data[0]->total->bayar);
            $printer->text("Kembali: ". $data->data[0]->total->kembali);
            $printer->text("\n");



            // Pesan penutup
            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Terima kasih telah berbelanja\n");
            $printer->text($data->data[0]->tanggal."\n");
            $printer->feed(2);
            $printer -> cut();
            $printer -> close();
        } finally {
            $printer -> close();
        }
    }
}
