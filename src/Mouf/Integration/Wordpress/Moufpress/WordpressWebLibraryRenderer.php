<?php
namespace Mouf\Integration\Wordpress\Moufpress;

use Mouf\Html\Utils\WebLibraryManager\WebLibraryInterface;

use Mouf\Html\Utils\WebLibraryManager\WebLibraryRendererInterface;

/**
 * The WordpressWebLibraryRenderer class is the Wordpress way of adding JS ans CSS files.
 *  
 * @author David Négrier
 */
class WordpressWebLibraryRenderer implements WebLibraryRendererInterface {
	
	private static $count = 0;
	
	/**
	 * A map of WebLibraryInterface=>name.
	 * @var \SplObjectStorage
	 */
	private $webLibraryNames;
	
	/**
	 * A map associating the Wordpress name of a enqueued script/style with the WebLibrary that replaces it.
	 * This is useful because there are special use cases where a WebLibrary overrides an existing
	 * library managed by Wordpress.
	 * 
	 * @var array<string, WebLibraryInterface>
	 */
	private $replacedWebLibrary;
	
	/**
	 * 
	 * @param array<string, WebLibraryInterface> $replacedWebLibrary A map associating the Wordpress name of a enqueued script/style with the WebLibrary that replaces it. This is useful because there are special use cases where a WebLibrary overrides an existing library managed by Wordpress.
	 */
	public function __construct($replacedWebLibrary = array()) {
		$this->replacedWebLibrary = $replacedWebLibrary;
		
		// Let's load the list of replaced web libraries.
		$this->webLibraryNames = new \SplObjectStorage();
	}

	private $initDone = false;
	
	private function init() {
		if ($this->initDone) {
			return;
		}
		$webLibraryNames = $this->webLibraryNames;
		array_walk($this->replacedWebLibrary, function(WebLibraryInterface $webLibrary, $name) use($webLibraryNames) {
			// Let's replace all the libs declared in Wordpress with our libs instead.
			$this->registerWordpressLib($webLibrary, $name);
		});
		$this->webLibraryNames = $webLibraryNames;
		
		$this->initDone = true;
	}
	
	/**
	 * Registers a weblibrary in Wordpress (using wp_register_script and wp_register_style).
	 * Uses $name is the name of passed. Otherwise, the name is autogenerated.
	 * 
	 * @param WebLibraryInterface $webLibrary
	 * @param string $name
	 * @return string The Wordpress name for the Weblibrary.
	 */
	private function registerWordpressLib(WebLibraryInterface $webLibrary, $name = null) {
		if ($this->webLibraryNames->contains($webLibrary)) {
			return $this->webLibraryNames[$webLibrary];
		}
		
		if ($name == null) {
			$name = 'moufpress_'.self::$count;
			self::$count++;
		}
		$this->webLibraryNames->attach($webLibrary, $name);
		
		$files = $webLibrary->getJsFiles();
		
		// We must map each file to one script in Wordpress.
		// We must add dependencies between these files.
		$lastDependencyName = null;
		$cnt = 0;
		
		$rootUrl = get_bloginfo('url');
		
		foreach ($files as $file) {
			// If this is the last:
			if ($cnt == count($files)-1) {
				$wpName = $name;
			} else {
				$wpName = $name."_".$cnt;
			}
			$dependencies = [];
			if ($lastDependencyName) {
				$dependencies[] = $lastDependencyName;
			}
			
			if (wp_script_is( $wpName, 'registered' )) {
				wp_deregister_script($wpName);
			}
			
			if(strpos($file, 'http://') === false && strpos($file, 'https://') === false && strpos($file, '/') !== 0) {
				wp_register_script($wpName, $rootUrl.'/'.$file, $dependencies);
			} else {
				wp_register_script($wpName, $file, $dependencies);
			}
			
			$lastDependencyName = $wpName;
			$cnt++;
		}
		
		// Same thing for CSS:
		$files = $webLibrary->getCssFiles();
		
		// We must map each file to one script in Wordpress.
		// We must add dependencies between these files.
		$lastDependencyName = null;
		$cnt = 0;
		
		foreach ($files as $file) {
			// If this is the last:
			if ($cnt == count($files)-1) {
				$wpName = $name;
			} else {
				$wpName = $name."_".$cnt;
			}
			$dependencies = [];
			if ($lastDependencyName) {
				$dependencies[] = $lastDependencyName;
			}
			
			if (wp_style_is( $wpName, 'registered' )) {
				wp_deregister_style($wpName);
			}
			
			if(strpos($file, 'http://') === false && strpos($file, 'https://') === false && strpos($file, '/') !== 0) {
				wp_register_style($wpName, $rootUrl.'/'.$file, $dependencies);
			} else {
				wp_register_style($wpName, $file, $dependencies);
			}
				
			$lastDependencyName = $wpName;
			$cnt++;
		}
		
		return $name; 
	}
	
	/**
	 * Renders the CSS part of a web library.
	 *
	 * @param WebLibrary $webLibrary
	 */
	public function toCssHtml(WebLibraryInterface $webLibrary) {
		$this->init();
		$name = $this->registerWordpressLib($webLibrary);
		wp_enqueue_style($name);
	}
	
	/**
	 * Renders the JS part of a web library.
	 *
	 * @param WebLibrary $webLibrary
	 */
	public function toJsHtml(WebLibraryInterface $webLibrary) {
		$this->init();
		$name = $this->registerWordpressLib($webLibrary);
		wp_enqueue_script($name);
	}
	
	/**
	 * Renders any additional HTML that should be outputed below the JS and CSS part.
	 *
	 * @param WebLibrary $webLibrary
	 */
	public function toAdditionalHtml(WebLibraryInterface $webLibrary) {
		return "";
	}
	
}