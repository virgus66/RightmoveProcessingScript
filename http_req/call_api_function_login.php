<?php

function ucadoApiLogin ($endpoint, $param) {

    $o = $param;
    $o = json_encode($o);

            $headers[]  = 'UcaDo-Auth: '.AUTH;
            $headers[]  = 'Content-Type: application/json';
            //$headers[]  = 'UcaDo-User-Token: '. $usertoken;

            $ch = curl_init(); 
            curl_setopt($ch, CURLOPT_URL, SERVER.$endpoint); 
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $o);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch); 
            curl_close($ch);

            $output = json_decode($output);    

            //print_r($output);
            return( $output );
}

?>