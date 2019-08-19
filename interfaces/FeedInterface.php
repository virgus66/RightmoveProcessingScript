<?php

interface FeedInterface {  
  public function processFeed($xml);
  public function archivePlotsNotFoundInFeed();
  public function plotsInFeed($xml);
  public function displayErrors();
}