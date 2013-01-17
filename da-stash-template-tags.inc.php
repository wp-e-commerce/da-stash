<?php

function get_da_stash_connect_uri() {
	return DA_Stash::get_redirect_uri('authorize-user');
}

function get_da_stash_api_uri() {
	return DA_Stash::get_redirect_uri('');
}