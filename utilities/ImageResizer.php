<?php
namespace ImageResizer {

class ImageResizer {
    protected $desired = 800;
    protected $url;
    public $wider_dimension;
    public $resized_img_b64;

    public function __construct($filePath) {
        $this->url   = $filePath;
        $this->wider_dimension = $this->ifNeedResize();
    }
    
    public function load() {
        $image    = imagecreatefromjpeg( $this->url );
        $origin_w = imagesx($image);
        $origin_h = imagesy($image);
        //    h   -   origin_h
        //    w   -   origin_w
        if ( $this->wider_dimension == 'width'  ) {
            $this->width = $this->desired;
            $this->height = (($this->width * $origin_h) / $origin_w);
        }

        if ( $this->wider_dimension == 'height' ) {
            $this->height = $this->desired;
            $this->width = (($this->height * $origin_w) / $origin_h);
        }

        if ( $this->wider_dimension == null ) {
            $this->width = $origin_w;
            $this->height = $origin_h;
        }

        $new_img = imagecreatetruecolor($this->width, $this->height);
        imagecopyresized($new_img, $image, 0, 0, 0, 0, 
            $this->width, $this->height,
            $origin_w, $origin_h
        );
        $this->buffer_out( $new_img );
    }

    public function countDimensions() {

    }

    public function buffer_out( $img ) {
        ob_start();
            imagejpeg($img);
            $data = ob_get_contents();
        ob_end_clean();

        $this->resized_img_b64 = base64_encode($data);
    }

    public function ifNeedResize() {
        $width = getimagesize($this->url)[0];
        $height = getimagesize($this->url)[1];

        if ( $width > 800 || $height > 800 ) {
            return ( ($width - $height) >= 0 ) ? 'width' : 'height';
        } else return false;
    }
}

}


/* TEST

$ir = new ImageResizer('./test_images/style_39_55_IMG_2.jpg');
$ir->load(); 
echo $ir->resized_img_b64;
*/

?>
