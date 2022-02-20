# iamedu.tools.certificate
Tools to generate event certificate

## Source
> https://github.com/zedomel/certificate-generator

We use the source from the link, but we modify the PDF generator using FPDF library

## Add font
To add font, you can convert .ttf file to .php, and save it into _font_ folder. One of online converters you can use is: `http://www.fpdf.org/makefont/`

## Install source
> composer install

## Run Script
You have to prepare `settings.json` first  
Below is the explanation of its properties

> - ### parameters  
>   - **event_name**: _The name of event that will be certified_  
>   - **template**: _Filename of certificate template in pdf format_  
>   - **data**: _CSV filename that contain certificate recipient data that will be processed by generator_  
>   - **email_col**: _Column name of email address within csv file_  
>   - **subject**: _Email subject that will be sent to the recipients_  
>   - **email_message**: _Email body that will be sent to the recipients. might be in html format or just plain text_  
>   - **replyto**: _Email sender that will be seen to the recipient_  
>   - **attachments**: _List of attachments that will be sent to the recipients (in array)_  
>  
>  
> - ### writer
>   - **pdf_size**: _Pixel length of pdf size in array. [0] = width, [1] = height_  
>   - **cert_id**: _Format of certificate id. Put {{seq}} string as placeholder of the sequence_  
>   - **fonts**: _List of fonts that will be used by the generator_  
>   - **texts**: _Contain objects that will be written in certificate_  
>     - **pos_x**: _x pixel position. start from previous text_   
>     - **pos_y**: _y pixel position. always start from the 0 pixel of column_   
>     - **value_col**: _Column name of data within csv file that will be written into pdf_   
>     - **font**: _Font style that will be used. Must be registered in `writer.fonts`_   
>     - **size**: _Font Size_   
>     - **align**: _L (left) | C (center) | R (right) align_  
>  
You can see the sample of `settings.json` in `input/sample/settings.json`

Run the script with PHP command line with options below

>  - **-f**: _Required. Subfolder that contain settings.json and other needed files_
>  - **-t**: _Optional. Use this options to test the generator. It will only generate certificate file without send the file to the recipients_
>  - **-n**: _Optional. Number of top data within csv file that will be processed_

Here is the example how to run the script 
> php certgen.php -f tangsel_inter/admin -t -n 1
