<?php

if (!class_exists('options'))
{
	exit('This killboard is not supported (options package missing)!');
}
options::cat('Advanced', 'Posting Options', 'Scouts');
options::fadd('Enable Scouts', 'scouts', 'checkbox');
options::fadd('Require password for Scouts', 'scouts_pw', 'checkbox');
options::fadd('Scout post password', 'scouts_password', 'edit');
?>