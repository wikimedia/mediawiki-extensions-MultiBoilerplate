<?php
/**
 * Hooks for MultiBoilerplate extension
 *
 * * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Extensions
 *
 * @TODO de-duplicate code between this and SpecialBoilerplates
 */

class MultiBoilerplateHooks {


	/**
	 * EditPage::showEditForm:initial hook
	 *
	 * Generate a boilerplate selection input on top of the edit page
	 *
	 * @param EditPage $editPage the current EditPage object.
	 * @param OutputPage $out object.
	 * @return true
	 */
	public static function onEditPageShowEditFormInitial( EditPage $editPage, OutputPage $out ) {
		global $wgParser, $wgMultiBoilerplateOptions, $wgMultiBoilerplateOverwrite;
		$title = $out->getTitle();
		$request = $out->getRequest();

		// If $wgMultiBoilerplateOverwrite is true then detect whether
		// the current page exists or not and if it does return true
		// to end execution of this function.
		if ( !$wgMultiBoilerplateOverwrite && $title->exists() ) {
			return true;
		}

		// Generate the options list used inside the boilerplate selection box.
		// If $wgMultiBoilerplateOptions is an array then use that, else fall back
		// to the MediaWiki:Multiboilerplate message.
		if ( is_array( $wgMultiBoilerplateOptions ) ) {
			$options = '';
			foreach ( $wgMultiBoilerplateOptions as $name => $template ) {
				$selected = false;
				if ( $request->getVal( 'boilerplate' ) === $template ) {
					$selected = true;
				}
				$options .= Xml::option( $name, $template, $selected );
			}
		} else {
			$things = wfMessage( 'multiboilerplate' )->inContentLanguage()->text();
			$options = '';
			$headingFound = 0;
			$things = explode( "\n", str_replace(
				"\r", "\n", str_replace( "\r\n", "\n", $things )
			) ); // Ensure line-endings are \n
			foreach ( $things as $row ) {
				if ( preg_match( '/==\s*(.*)\s*==/', $row, $optGroupText ) ) {
					if ( $headingFound ) {
						$options .= '</optgroup>';
					}
					$headingFound = true;
					$options.='<optgroup label="' . htmlspecialchars( $optGroupText[1] ) . '">';
				} elseif ( substr( ltrim( $row ), 0, 1 ) === '*' ) {
					$row = ltrim( $row, '* ' ); // Remove astersk & spacing from start of line.
					$rowParts = explode( '|', $row );
					if ( !isset( $rowParts[ 1 ] ) ) {
						return true; // Invalid syntax, abort
					}
					$selected = false;
					if ( $request->getVal( 'boilerplate' ) === $rowParts[ 1 ] ) {
						$selected = true;
					}
					$options .= Xml::option( $rowParts[ 0 ], $rowParts[ 1 ], $selected );
				}

			}
			if ( $headingFound ) {
				$options .= '</optgroup>';
			}
		}

		// No options found in either configuration file, abort.
		if ( $options == '' ) {
			return true;
		}

		// Append the selection form to the top of the edit page.
		$editPage->editFormPageTop .=
			Xml::openElement( 'form', array(
				'id' => 'multiboilerplateform',
				'name' => 'multiboilerplateform',
				'method' => 'get',
				'action' => $title->getEditURL() )
			)
			. Xml::openElement( 'fieldset' )
			. Xml::element( 'legend', null, wfMessage( 'multiboilerplate-legend' ) )
			. Xml::openElement( 'label' )
			. wfMessage( 'multiboilerplate-label' )->text()
			. Xml::openElement( 'select', array( 'name' => 'boilerplate' ) )
			. $options
			. Xml::closeElement( 'select' )
			. Xml::closeElement( 'label' )
			. ' '
			. Html::Hidden( 'action', 'edit' )
			. Html::Hidden( 'title', $request->getText( 'title' ) )
			. Xml::submitButton( wfMessage( 'multiboilerplate-submit' ) )
			. Xml::closeElement( 'fieldset' )
			. Xml::closeElement( 'form' );

		// If the Load button has been pushed replace the article text with the boilerplate.
		if ( $request->getText( 'boilerplate', false ) ) {
			$boilerplateTitle = Title::newFromText( $request->getVal( 'boilerplate' ) );
			if ( !$boilerplateTitle->exists() ) {
				$out->addHTML(
					'<strong class="error">'
					. $out->msg( 'multiboilerplate-nonexistant-page' )->params(
						$request->getText( 'boilerplate')
					)->text()
					. '</strong>'
				);
			} else {
				$boilerplate   = new WikiPage( $boilerplateTitle );
				$parser        = $wgParser->getFreshParser();  // Since MW 1.24
				$parserOptions = is_null( $parser->getOptions() ) ? new ParserOptions : $parser->getOptions();
				$content       = $parser->getPreloadText(
					$boilerplate->getContent()->getWikitextForTransclusion(),
					$boilerplateTitle,
					$parserOptions
				);

				$editPage->textbox1 = $content;
			}
		}

		return true;
	}

}