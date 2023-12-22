<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
/* The class name must be the name of your module, without spaces. */
/* It must also extend the "Module" class. This gives your module access to API functions */
class base64encdec extends Controller
{
    protected $endpointRoutes = ['getContents', 'encode', 'decode'];

    public function getContents()  // This is the function that will be executed when you send the request "getContents".
    {
        $this->responseHandler->setData(array("success" => true,    // define $this->response. We can use an array to set multiple responses.
                                "greeting" => "Hey there!",
                                "content" => "This is the HTML template for your new module! The example shows you the basics of using HTML, AngularJS and PHP to seamlessly pass information to and from Javascript and PHP and output it to HTML."));
    }

    public function encode()  
    {
        $inputContent = $this->request['data'];
        
        $result = base64_encode( $inputContent );
        
        $this->responseHandler->setData(array("success" => true,
                                "content" => $result));
    }

   public function decode()  
    {
        $inputContent = $this->request['data'];
        
        $result = base64_decode( $inputContent );
        
        $this->responseHandler->setData(array("success" => true,
                                "content" => $result));
    }

}




