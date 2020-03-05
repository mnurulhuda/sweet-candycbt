<?php
if ($_POST) {
    require "../config/config.database.php";

    $id_mapel = $_POST['id_mapel'];
    $namaFile = $_FILES['word_file']['name'];
    $tmp      = $_FILES['word_file']['tmp_name'];

    $target_dir = "../files/";

    $target_file = $target_dir . $namaFile;

    move_uploaded_file($tmp, $target_file);

    $question_split = "/Soal:[0-9]+\)/";
    $option_split   = "/[A-E]:/";
    $correct_split  = "/Kunci:/";
    $audio_split    = "/Audio:/";

    $j_opt = 5;

    $info          = pathinfo($target_file);
    $new_name      = $info['filename'] . '.Zip';
    $new_name_path = $target_dir . $new_name;
    rename($target_file, $new_name_path);

    $zip = new ZipArchive;
    if ($zip->open($new_name_path) === true) {
        $zip->extractTo($target_dir);
        $zip->close();

        $word_xml            = $target_dir . "word/document.xml";
        $word_xml_relational = $target_dir . "word/_rels/document.xml.rels";

        $content = file_get_contents($word_xml);

        $content         = htmlentities(strip_tags($content, "<a:blip>"));
        $xml             = simplexml_load_file($word_xml_relational);
        $supported_image = array(
            'gif',
            'jpg',
            'jpeg',
            'png',
        );

        $relation_image = array();
        foreach ($xml as $key => $qjd) {
            $ext = strtolower(pathinfo($qjd['Target'], PATHINFO_EXTENSION));
            if (in_array($ext, $supported_image)) {
                $id     = xml_attribute($qjd, 'Id');
                $target = xml_attribute($qjd, 'Target');

                $relation_image[$id] = $target;
            }

        }
        $word_folder    = $target_dir . "word";
        $prop_folder    = $target_dir . "docProps";
        $relat_folder   = $target_dir . "_rels";
        $content_folder = $target_dir . "[Content_Types].xml";

        $rand_inc_number = 1;
        foreach ($relation_image as $key => $value) {
            $rplc_str      = '&lt;a:blip r:embed=&quot;' . $key . '&quot; cstate=&quot;print&quot;/&gt;';
            $rplc_str2     = '&lt;a:blip r:embed=&quot;' . $key . '&quot;&gt;&lt;/a:blip&gt;';
            $rplc_str3     = '&lt;a:blip r:embed=&quot;' . $key . '&quot;/&gt;';
            $ext_img       = strtolower(pathinfo($value, PATHINFO_EXTENSION));
            $imagenew_name = time() . $rand_inc_number . "." . $ext_img;
            $old_path      = $word_folder . "/" . $value;
            $new_path      = $target_dir . $imagenew_name;

            rename($old_path, $new_path);
            $img = '<img src="../files/' . $imagenew_name . '">';

            $content = str_replace($rplc_str, $img, $content);
            $content = str_replace($rplc_str2, $img, $content);
            $content = str_replace($rplc_str3, $img, $content);
            $rand_inc_number++;
        }

        rrmdir($word_folder);
        rrmdir($relat_folder);
        rrmdir($prop_folder);
        rrmdir($content_folder);
        rrmdir($new_name_path);

        $content2 = $content;
        $expl     = array_filter(preg_split($question_split, $content));
        if (trim($expl[0]) == '') {
            unset($expl[0]);
        }
        $expl     = array_values($expl);
        $explflag = get_numerics($content2);

        // if (count($expl) < 40) {
        //     echo json_encode(["status" => "0", "hasil" => "Jumlah soal kurang dari 40."]);
        //     exit;
        // }

        foreach ($expl as $ekey => $value) {
            $cqno = str_replace('Soal:', '', $explflag[$ekey]);
            $cqno = str_replace(')', '', $cqno);

            if ($cqno != ($ekey + 1)) {
                echo json_encode(["status" => "0", "hasil" => "Format soal salah pada soal nomor " . ($ekey + 1) . " atau soal tidak ditemukan."]);
                exit;
            }

            $quesions[$cqno] = array_filter(preg_split($option_split, $value));
            $jindex          = count($quesions[$cqno]);

            $jpil = $jindex - 1;
            if (($jindex > 1) && ($jindex < ($j_opt + 1))) {
                echo json_encode(["status" => "0", "hasil" => "Jumlah pilihan jawaban pada soal nomor " . $cqno . " hanya ada " . $jpil . ". Sedangkan di bank soal jumlah pilihan adalah " . $j_opt . "."]);
                exit;
            } else if ($jindex > $j_opt + 3) {
                echo json_encode(["status" => "0", "hasil" => "Format soal salah pada soal nomor " . ($cqno + 1) . "."]);
                exit;
            }

            foreach ($quesions as $key => $options) {
                $option_count = count($options);

                foreach ($options as $key_option => $val_option) {
                    if ($option_count > 1) {
                        if ($key_option == ($option_count - 1)) {
                            if (preg_match($correct_split, $val_option, $match)) {
                                $correct = array_filter(preg_split($correct_split, $val_option));

                                $options[$key_option] = $correct[0];
                                $options['kunci']     = trim($correct[1]);
                            } else {
                                echo json_encode(["status" => "0", "hasil" => "Kunci jawaban pada soal nomor " . $cqno . " tidak ada."]);
                                exit;
                            }
                        } else if ($key_option == 0) {
                            if (preg_match($audio_split, $val_option, $match)) {
                                $audio = array_filter(preg_split($audio_split, $val_option));

                                $options[$key_option] = $audio[0];
                                $options['audio']     = $audio[1];
                            }
                        }
                    } else {
                        $options[$key_option] = $val_option;
                    }

                    // $options[$key_option] = str_replace('"','&#34;', $options[$key_option] );
                    $options[$key_option] = str_replace("‘", '&#39;', $options[$key_option]);
                    $options[$key_option] = str_replace("’", '&#39;', $options[$key_option]);
                    $options[$key_option] = str_replace("â€œ", '&#34;', $options[$key_option]);
                    $options[$key_option] = str_replace("â€˜", '&#39;', $options[$key_option]);
                    $options[$key_option] = str_replace("â€™", '&#39;', $options[$key_option]);
                    $options[$key_option] = str_replace("â€", '&#34;', $options[$key_option]);
                    $options[$key_option] = str_replace("'", "&#39;", $options[$key_option]);
                    $options[$key_option] = str_replace("\n", "<br>", $options[$key_option]);

                    $options[$key_option] = str_replace("&amp;lt;", "<", $options[$key_option]);
                    $options[$key_option] = str_replace("&amp;gt;", ">", $options[$key_option]);
                    $options[$key_option] = str_replace("'", "&#39;", $options[$key_option]);
                    // $options[$key_option] = str_replace(" ", "", $options[$key_option] );
                    $options[$key_option] = str_replace(" &ndash;", "-", $options[$key_option]);
                }
            }
            $quesions[$cqno] = $options;
        }
        $hasil = ["status" => "1", "id_mapel" => $id_mapel, "hasil" => "Jumlah Soal tersimpan = " . $cqno];
    } else {
        $hasil = ["status" => "0", "hasil" => "Gagal"];
    }

    $mapel = mysqli_query($koneksi, "SELECT jml_soal FROM mapel WHERE id_mapel = $id_mapel");

    $jml_soal = mysqli_fetch_array($mapel)[0];
    if (count($quesions) < $jml_soal) {
        echo json_encode(["status" => "0", "hasil" => "Jumlah soal kurang. Jumlah soal di bank soal = " . $jml_soal . ". Soal diimport = " . count($quesions) . "."]);
        exit;
    }

    $pg = 0;
    $es = 0;
    foreach ($quesions as $key => $value) {
        if (count($value) == 1) {
            $jns = 2;
            $es++;
            $no             = $es;
            $value[1]       = '';
            $value[2]       = '';
            $value[3]       = '';
            $value[4]       = '';
            $value[5]       = '';
            $value['kunci'] = '';
        } else {
            $no  = $key;
            $jns = 1;
            $pg++;
        }

        if (!isset($value['audio'])) {
            $value['audio'] = '';
        }

        $cari = mysqli_query($koneksi, "SELECT * FROM soal WHERE id_mapel = $id_mapel AND nomor = $no AND jenis = $jns");
        if (mysqli_num_rows($cari) > 0) {
            mysqli_query($koneksi, "DELETE FROM soal WHERE id_mapel = $id_mapel AND nomor = $no");
        }
        $exec = mysqli_query($koneksi, "INSERT INTO soal (id_mapel,nomor,soal,pilA,pilB,pilC,pilD,pilE,jawaban,jenis,file1) VALUES ('$id_mapel','$no','$value[0]','$value[1]','$value[2]','$value[3]','$value[4]','$value[5]','$value[kunci]','$jns','$value[audio]')");
    }
    $hasil["hasil"] = "Jumlah Soal Pilihan Ganda = " . $pg . ".\nJumlah soal Essai = " . $es;
    echo json_encode($hasil);
} else {
    echo "404";
}

function xml_attribute($object, $attribute)
{
    if (isset($object[$attribute])) {
        return (string) $object[$attribute];
    }

}

function get_numerics($str)
{
    preg_match_all('/Soal:\d+\)/', $str, $matches);
    return $matches[0];
}

function rrmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir . "/" . $object) == "dir") {
                    rrmdir($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }

            }
        }
        reset($objects);
        if ($dir != "uploads") {
            rmdir($dir);
        }
    } else {

        unlink($dir);
    }
}
