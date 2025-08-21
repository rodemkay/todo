<?php
/**
 * Plan Parser - Konvertiert HTML-Pläne zu strukturierten Daten
 * 
 * @package WP_Project_Todos
 */

namespace WP_Project_Todos;

class Plan_Parser {
    
    /**
     * Parsert HTML-Plan zu strukturierten Daten
     */
    public function parse_html_to_structure($html) {
        if (empty($html)) {
            return $this->get_empty_structure();
        }
        
        // DOM Parser für HTML
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $structure = [
            'title' => $this->extract_title($dom),
            'goals' => $this->extract_goals($dom),
            'steps' => $this->extract_steps($dom),
            'requirements' => $this->extract_requirements($dom),
            'notes' => $this->extract_notes($dom),
            'risks' => $this->extract_risks($dom),
            'timeline' => $this->extract_timeline($dom),
            'user_feedback' => '', // Leer für neue Kommentare
            'created_at' => date('Y-m-d H:i:s'),
            'raw_html' => $html // Backup des originalen HTML
        ];
        
        return $structure;
    }
    
    /**
     * Konvertiert strukturierte Daten zurück zu HTML
     */
    public function structure_to_html($structure) {
        $html = $this->generate_plan_html($structure);
        return $html;
    }
    
    /**
     * Leere Struktur für neue Pläne
     */
    private function get_empty_structure() {
        return [
            'title' => '',
            'goals' => [],
            'steps' => [],
            'requirements' => [],
            'notes' => [],
            'risks' => [],
            'timeline' => '',
            'user_feedback' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'raw_html' => ''
        ];
    }
    
    /**
     * Extrahiert Titel aus HTML
     */
    private function extract_title($dom) {
        $h1_nodes = $dom->getElementsByTagName('h1');
        if ($h1_nodes->length > 0) {
            return trim($h1_nodes->item(0)->textContent);
        }
        
        $h2_nodes = $dom->getElementsByTagName('h2');
        if ($h2_nodes->length > 0) {
            $title = trim($h2_nodes->item(0)->textContent);
            // Entferne Emojis und Prefixe
            $title = preg_replace('/^[📋🎯⚡️\s]*/', '', $title);
            return $title;
        }
        
        return 'Implementierungsplan';
    }
    
    /**
     * Extrahiert Ziele/Objectives
     */
    private function extract_goals($dom) {
        $goals = [];
        
        // Suche nach Abschnitten mit Zielen
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//h2[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'ziel') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'objective')]");
        
        foreach ($nodes as $node) {
            $goals = array_merge($goals, $this->extract_list_items_after_heading($node));
        }
        
        return $goals;
    }
    
    /**
     * Extrahiert Implementierungsschritte
     */
    private function extract_steps($dom) {
        $steps = [];
        
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//h2[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'schritt') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'implementation')] | //h3[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'schritt')]");
        
        foreach ($nodes as $node) {
            $steps = array_merge($steps, $this->extract_list_items_after_heading($node));
        }
        
        // Fallback: Suche nach nummerierten Listen
        if (empty($steps)) {
            $ol_nodes = $dom->getElementsByTagName('ol');
            foreach ($ol_nodes as $ol) {
                $li_nodes = $ol->getElementsByTagName('li');
                foreach ($li_nodes as $li) {
                    $steps[] = trim($li->textContent);
                }
            }
        }
        
        return $steps;
    }
    
    /**
     * Extrahiert Anforderungen
     */
    private function extract_requirements($dom) {
        $requirements = [];
        
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//h2[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'anforderung') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'requirement')]");
        
        foreach ($nodes as $node) {
            $requirements = array_merge($requirements, $this->extract_list_items_after_heading($node));
        }
        
        return $requirements;
    }
    
    /**
     * Extrahiert Notizen
     */
    private function extract_notes($dom) {
        $notes = [];
        
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//h2[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'notiz') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'note')] | //h3[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'wichtig')]");
        
        foreach ($nodes as $node) {
            // Für Notizen auch Paragraphen extrahieren
            $sibling = $node->nextSibling;
            while ($sibling) {
                if ($sibling->nodeType === XML_ELEMENT_NODE) {
                    if ($sibling->nodeName === 'p') {
                        $notes[] = trim($sibling->textContent);
                    } elseif (in_array($sibling->nodeName, ['h2', 'h3', 'h4'])) {
                        break; // Nächster Abschnitt erreicht
                    }
                }
                $sibling = $sibling->nextSibling;
            }
        }
        
        return $notes;
    }
    
    /**
     * Extrahiert Risiken
     */
    private function extract_risks($dom) {
        $risks = [];
        
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//h2[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'risiko') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'problem')] | //h3[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'achtung')]");
        
        foreach ($nodes as $node) {
            $risks = array_merge($risks, $this->extract_list_items_after_heading($node));
        }
        
        return $risks;
    }
    
    /**
     * Extrahiert Zeitplan
     */
    private function extract_timeline($dom) {
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//h2[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'zeit') or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'timeline')]");
        
        $timeline = '';
        foreach ($nodes as $node) {
            $sibling = $node->nextSibling;
            while ($sibling) {
                if ($sibling->nodeType === XML_ELEMENT_NODE) {
                    if ($sibling->nodeName === 'p') {
                        $timeline .= trim($sibling->textContent) . "\n";
                    } elseif (in_array($sibling->nodeName, ['h2', 'h3', 'h4'])) {
                        break;
                    }
                }
                $sibling = $sibling->nextSibling;
            }
        }
        
        return trim($timeline);
    }
    
    /**
     * Hilfsfunktion: Extrahiert Liste nach Überschrift
     */
    private function extract_list_items_after_heading($heading_node) {
        $items = [];
        $sibling = $heading_node->nextSibling;
        
        while ($sibling) {
            if ($sibling->nodeType === XML_ELEMENT_NODE) {
                if (in_array($sibling->nodeName, ['ul', 'ol'])) {
                    $li_nodes = $sibling->getElementsByTagName('li');
                    foreach ($li_nodes as $li) {
                        $items[] = trim($li->textContent);
                    }
                } elseif (in_array($sibling->nodeName, ['h2', 'h3', 'h4'])) {
                    break; // Nächster Abschnitt erreicht
                }
            }
            $sibling = $sibling->nextSibling;
        }
        
        return $items;
    }
    
    /**
     * Generiert HTML aus strukturierten Daten
     */
    private function generate_plan_html($structure) {
        $html = '<div class="structured-plan">';
        
        // Header
        $html .= '<div class="plan-header">';
        $html .= '<h1>📋 ' . esc_html($structure['title']) . '</h1>';
        $html .= '<div class="plan-meta">';
        $html .= '<span>📅 Erstellt: ' . esc_html($structure['created_at']) . '</span>';
        if (!empty($structure['timeline'])) {
            $html .= '<span>⏱️ Zeitplan: ' . esc_html($structure['timeline']) . '</span>';
        }
        $html .= '</div>';
        $html .= '</div>';
        
        // Ziele
        if (!empty($structure['goals'])) {
            $html .= '<section class="plan-goals">';
            $html .= '<h2>🎯 Ziele & Objectives</h2>';
            $html .= '<ul>';
            foreach ($structure['goals'] as $goal) {
                $html .= '<li>' . esc_html($goal) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</section>';
        }
        
        // Anforderungen
        if (!empty($structure['requirements'])) {
            $html .= '<section class="plan-requirements">';
            $html .= '<h2>📌 Anforderungen</h2>';
            $html .= '<ul>';
            foreach ($structure['requirements'] as $req) {
                $html .= '<li>' . esc_html($req) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</section>';
        }
        
        // Implementierungsschritte
        if (!empty($structure['steps'])) {
            $html .= '<section class="plan-steps">';
            $html .= '<h2>🔨 Implementierungsschritte</h2>';
            $html .= '<ol>';
            foreach ($structure['steps'] as $step) {
                $html .= '<li>' . esc_html($step) . '</li>';
            }
            $html .= '</ol>';
            $html .= '</section>';
        }
        
        // Risiken
        if (!empty($structure['risks'])) {
            $html .= '<section class="plan-risks">';
            $html .= '<h2>⚠️ Potenzielle Risiken</h2>';
            $html .= '<ul>';
            foreach ($structure['risks'] as $risk) {
                $html .= '<li>' . esc_html($risk) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</section>';
        }
        
        // Notizen
        if (!empty($structure['notes'])) {
            $html .= '<section class="plan-notes">';
            $html .= '<h2>📝 Wichtige Notizen</h2>';
            foreach ($structure['notes'] as $note) {
                $html .= '<p>' . esc_html($note) . '</p>';
            }
            $html .= '</section>';
        }
        
        // Benutzer-Feedback
        if (!empty($structure['user_feedback'])) {
            $html .= '<section class="user-feedback">';
            $html .= '<h2>💬 Benutzer-Feedback</h2>';
            $html .= '<div class="feedback-content">';
            $html .= nl2br(esc_html($structure['user_feedback']));
            $html .= '</div>';
            $html .= '</section>';
        }
        
        $html .= '</div>';
        
        // CSS einbinden
        $html .= '<style>
        .structured-plan { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            max-width: 900px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .plan-header { 
            border-bottom: 3px solid #2271b1; 
            padding-bottom: 20px; 
            margin-bottom: 30px; 
        }
        .plan-header h1 { 
            color: #2271b1; 
            margin: 0 0 15px 0; 
            font-size: 2.2em; 
        }
        .plan-meta { 
            display: flex; 
            gap: 20px; 
            color: #666; 
            font-size: 0.9em; 
        }
        .structured-plan section { 
            margin-bottom: 30px; 
        }
        .structured-plan h2 { 
            color: #135e96; 
            border-left: 4px solid #2271b1; 
            padding-left: 15px; 
            margin-bottom: 15px; 
        }
        .structured-plan ul, .structured-plan ol { 
            padding-left: 20px; 
        }
        .structured-plan li { 
            margin-bottom: 8px; 
            line-height: 1.6; 
        }
        .user-feedback { 
            background: #f0f8ff; 
            border: 2px solid #2271b1; 
            border-radius: 8px; 
            padding: 20px; 
        }
        .feedback-content { 
            background: white; 
            padding: 15px; 
            border-radius: 5px; 
            border-left: 4px solid #2271b1; 
        }
        </style>';
        
        return $html;
    }
}