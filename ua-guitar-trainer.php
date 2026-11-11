
<?php
/**
 * Plugin Name: UA Guitar Trainer
 * Description: Інтерактивний тренажер гітари: стилі, акорди, власні послідовності, ритм і акустична/електрична гітара.
 * Version: 1.0.0
 * Author: UA Tools
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

final class UA_Guitar_Trainer {

    public function __construct() {
        add_shortcode('ua_guitar_trainer', array($this, 'render'));
    }

    public function render() {
        static $instance = 0;
        $instance++;
        $id = 'ua-guitar-trainer-' . $instance . '-' . wp_rand(100, 9999);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($id); ?>" class="uag-wrap">
            <div class="uag-header">
                <div>
                    <div class="uag-brand">🎸 UA GUITAR TRAINER</div>
                    <div class="uag-subtitle">Твоя гітара. Твій ритм. Твоя музика.</div>
                </div>
                <div class="uag-instruments">
                    <button type="button" class="uag-instrument active" data-instrument="acoustic">Акустична</button>
                    <button type="button" class="uag-instrument" data-instrument="electric">Електрична</button>
                </div>
            </div>

            <div class="uag-modebar">
                <button type="button" class="uag-mode active" data-mode="styles">🎼 Грати за стилем</button>
                <button type="button" class="uag-mode" data-mode="custom">🎵 Свій варіант</button>
            </div>

            <section class="uag-panel uag-style-panel">
                <div class="uag-field">
                    <label>Музичний стиль</label>
                    <select class="uag-style"></select>
                </div>
                <div class="uag-field">
                    <label>Варіант виконання</label>
                    <select class="uag-variant"></select>
                </div>
                <div class="uag-description"></div>
            </section>

            <section class="uag-panel uag-custom-panel" style="display:none">
                <div class="uag-field">
                    <label>Додати акорд</label>
                    <div class="uag-addrow">
                        <select class="uag-chord-select"></select>
                        <button type="button" class="uag-add">+ Додати</button>
                    </div>
                </div>
                <div class="uag-field">
                    <label>Твоя послідовність акордів</label>
                    <div class="uag-sequence"></div>
                    <button type="button" class="uag-clear">Очистити послідовність</button>
                </div>
                <div class="uag-field">
                    <label>Ритмічний малюнок</label>
                    <select class="uag-custom-pattern">
                        <option value="basic">Рівномірний бій</option>
                        <option value="downup">Вниз — вгору</option>
                        <option value="ballad">Повільна балада</option>
                        <option value="rock">Рок-бій</option>
                        <option value="arpeggio">Перебір</option>
                    </select>
                </div>
            </section>

            <div class="uag-controls">
                <div class="uag-control">
                    <label>Швидкість: <strong class="uag-bpm-label">100 BPM</strong></label>
                    <input class="uag-bpm" type="range" min="40" max="220" value="100">
                    <div class="uag-range"><span>40</span><span>220 BPM</span></div>
                </div>
                <div class="uag-control">
                    <label>Гучність</label>
                    <input class="uag-volume" type="range" min="0" max="100" value="65">
                </div>
            </div>

            <div class="uag-stage">
                <div class="uag-now">
                    <span>ЗАРАЗ ГРАЄ</span>
                    <strong class="uag-current">—</strong>
                    <small class="uag-next">Наступний акорд: —</small>
                </div>
                <div class="uag-guitar">
                    <div class="uag-fretboard">
                        <div class="uag-strings">
                            <span></span><span></span><span></span>
                            <span></span><span></span><span></span>
                        </div>
                        <div class="uag-frets">
                            <i></i><i></i><i></i><i></i>
                        </div>
                        <div class="uag-fret-labels"><span>1</span><span>2</span><span>3</span><span>4</span></div>
                    </div>
                    <div class="uag-hand">✋</div>
                    <div class="uag-pick">♪</div>
                </div>
                <div class="uag-playing-info">
                    <span class="uag-direction">Очікування відтворення</span>
                    <span class="uag-beat">● ● ● ●</span>
                </div>
            </div>

            <div class="uag-chord-area">
                <div class="uag-chord-card">
                    <div class="uag-chord-title">Схема акорду</div>
                    <div class="uag-diagram"></div>
                    <div class="uag-chord-name">Оберіть акорд</div>
                </div>
                <div class="uag-help">
                    <strong>Як грати правою рукою</strong>
                    <p class="uag-instruction">Натисніть «Грати», щоб побачити напрямок руху руки та почути звучання.</p>
                    <div class="uag-arrows">
                        <span>↓ Вниз</span>
                        <span>↑ Вгору</span>
                        <span>♪ Перебір</span>
                    </div>
                </div>
            </div>

            <div class="uag-chord-list">
                <div class="uag-list-title">Послідовність акордів</div>
                <div class="uag-progress"></div>
            </div>

            <div class="uag-actions">
                <button type="button" class="uag-play">▶ ГРАТИ</button>
                <button type="button" class="uag-stop" disabled>■ СТОП</button>
                <button type="button" class="uag-save">♥ Зберегти</button>
                <button type="button" class="uag-load">Мої композиції</button>
            </div>
            <div class="uag-message" aria-live="polite"></div>
            <div class="uag-footer">UA Guitar Trainer · Звук генерується у браузері. API та платні сервіси не потрібні.</div>
        </div>

        <style>
        #<?php echo esc_attr($id); ?> {
            --uag-blue:#164b9b; --uag-yellow:#ffd43b; --uag-dark:#172033;
            font-family:Arial,sans-serif;color:var(--uag-dark);
            max-width:950px;margin:20px auto;background:#f4f7fb;
            border:1px solid #dce4ef;border-radius:18px;overflow:hidden;
            box-shadow:0 8px 28px rgba(20,45,80,.10);
        }
        #<?php echo esc_attr($id); ?> * {box-sizing:border-box}
        #<?php echo esc_attr($id); ?> button,
        #<?php echo esc_attr($id); ?> select {font:inherit}
        #<?php echo esc_attr($id); ?> button {cursor:pointer}
        #<?php echo esc_attr($id); ?> .uag-header {
            background:linear-gradient(120deg,#102e60,#1d5baa);color:white;
            padding:20px;display:flex;justify-content:space-between;gap:15px;
            align-items:center;flex-wrap:wrap
        }
        #<?php echo esc_attr($id); ?> .uag-brand {font-size:21px;font-weight:900;letter-spacing:1px}
        #<?php echo esc_attr($id); ?> .uag-subtitle {font-size:12px;opacity:.8;margin-top:5px}
        #<?php echo esc_attr($id); ?> .uag-instruments {display:flex;gap:6px}
        #<?php echo esc_attr($id); ?> .uag-instrument {
            border:1px solid #ffffff66;background:#ffffff15;color:white;
            border-radius:9px;padding:9px 12px;font-size:13px
        }
        #<?php echo esc_attr($id); ?> .uag-instrument.active {
            background:var(--uag-yellow);color:#172033;border-color:var(--uag-yellow);font-weight:bold
        }
        #<?php echo esc_attr($id); ?> .uag-modebar {display:flex;background:#e5ebf4;padding:10px;gap:8px}
        #<?php echo esc_attr($id); ?> .uag-mode {
            flex:1;padding:12px;border:0;border-radius:9px;background:transparent;color:#40516c;font-weight:bold
        }
        #<?php echo esc_attr($id); ?> .uag-mode.active {background:white;color:#164b9b;box-shadow:0 2px 8px #152c5018}
        #<?php echo esc_attr($id); ?> .uag-panel {padding:16px 20px;display:grid;grid-template-columns:1fr 1fr;gap:14px}
        #<?php echo esc_attr($id); ?> .uag-field label,
        #<?php echo esc_attr($id); ?> .uag-control label {display:block;font-weight:bold;font-size:13px;margin-bottom:7px}
        #<?php echo esc_attr($id); ?> select {
            width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:9px;background:white;color:#172033
        }
        #<?php echo esc_attr($id); ?> .uag-description {grid-column:1/-1;font-size:13px;color:#607087}
        #<?php echo esc_attr($id); ?> .uag-addrow {display:flex;gap:7px}
        #<?php echo esc_attr($id); ?> .uag-add {border:0;background:#164b9b;color:white;border-radius:8px;padding:0 15px;white-space:nowrap}
        #<?php echo esc_attr($id); ?> .uag-sequence {display:flex;gap:6px;flex-wrap:wrap;margin:8px 0}
        #<?php echo esc_attr($id); ?> .uag-seq-item {background:#164b9b;color:white;border-radius:7px;padding:7px 9px;font-weight:bold}
        #<?php echo esc_attr($id); ?> .uag-seq-item button {border:0;background:transparent;color:white;margin-left:5px}
        #<?php echo esc_attr($id); ?> .uag-clear {border:0;background:transparent;color:#b42318;padding:5px 0;font-size:12px}
        #<?php echo esc_attr($id); ?> .uag-controls {padding:0 20px 18px;display:grid;grid-template-columns:1fr 1fr;gap:20px}
        #<?php echo esc_attr($id); ?> input[type=range] {width:100%;accent-color:#164b9b}
        #<?php echo esc_attr($id); ?> .uag-range {display:flex;justify-content:space-between;color:#8090a5;font-size:11px}
        #<?php echo esc_attr($id); ?> .uag-stage {
            margin:0 16px;background:linear-gradient(145deg,#132b50,#244c7b);
            color:white;border-radius:15px;padding:18px;text-align:center;overflow:hidden
        }
        #<?php echo esc_attr($id); ?> .uag-now span {display:block;font-size:10px;letter-spacing:2px;opacity:.7}
        #<?php echo esc_attr($id); ?> .uag-current {display:block;font-size:44px;color:#ffdc54;line-height:1.25}
        #<?php echo esc_attr($id); ?> .uag-next {font-size:12px;opacity:.8}
        #<?php echo esc_attr($id); ?> .uag-guitar {
            height:115px;max-width:550px;margin:15px auto 5px;position:relative;
            border-radius:60px;background:linear-gradient(90deg,#9b5c2d,#d69b55,#9b5c2d);
            border:5px solid #70421f;box-shadow:inset 0 0 18px #49270d;overflow:hidden
        }
        #<?php echo esc_attr($id); ?> .uag-fretboard {
            position:absolute;left:8%;right:8%;top:15px;height:75px;
            background:linear-gradient(90deg,#34251e,#60402d);border:2px solid #b98a55
        }
        #<?php echo esc_attr($id); ?> .uag-strings {position:absolute;inset:0;display:flex;flex-direction:column;justify-content:space-around}
        #<?php echo esc_attr($id); ?> .uag-strings span {height:1px;background:#e8d9b8;box-shadow:0 1px 1px #111}
        #<?php echo esc_attr($id); ?> .uag-frets {position:absolute;inset:0;display:flex;justify-content:space-around}
        #<?php echo esc_attr($id); ?> .uag-frets i {height:100%;width:2px;background:#c8b49b;box-shadow:1px 0 2px #111}
        #<?php echo esc_attr($id); ?> .uag-fret-labels {position:absolute;bottom:-17px;left:0;right:0;display:flex;justify-content:space-around;color:white;font-size:9px}
        #<?php echo esc_attr($id); ?> .uag-hand {position:absolute;right:18%;top:17px;font-size:42px;transform:rotate(-35deg);opacity:.9;z-index:2}
        #<?php echo esc_attr($id); ?> .uag-pick {position:absolute;right:35%;top:32px;color:#ffe16b;font-size:25px;z-index:3}
        #<?php echo esc_attr($id); ?> .uag-stage.uag-down .uag-hand {animation:uagDown .22s ease}
        #<?php echo esc_attr($id); ?> .uag-stage.uag-up .uag-hand {animation:uagUp .22s ease}
        #<?php echo esc_attr($id); ?> .uag-stage.uag-down .uag-pick,
        #<?php echo esc_attr($id); ?> .uag-stage.uag-up .uag-pick {animation:uagPick .22s ease}
        @keyframes uagDown {0%{transform:translateY(-8px) rotate(-35deg)}100%{transform:translateY(10px) rotate(10deg)}}
        @keyframes uagUp {0%{transform:translateY(10px) rotate(10deg)}100%{transform:translateY(-8px) rotate(-35deg)}}
        @keyframes uagPick {50%{transform:scale(1.5)}}
        #<?php echo esc_attr($id); ?> .uag-playing-info {display:flex;justify-content:space-between;font-size:12px;opacity:.85}
        #<?php echo esc_attr($id); ?> .uag-beat {letter-spacing:5px;color:#ffdc54}
        #<?php echo esc_attr($id); ?> .uag-chord-area {display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:16px}
        #<?php echo esc_attr($id); ?> .uag-chord-card,
        #<?php echo esc_attr($id); ?> .uag-help {background:white;border:1px solid #e0e7ef;border-radius:12px;padding:14px;text-align:center}
        #<?php echo esc_attr($id); ?> .uag-chord-title,.uag-list-title {font-size:13px;font-weight:bold;color:#53647b}
        #<?php echo esc_attr($id); ?> .uag-diagram {height:125px;display:flex;align-items:center;justify-content:center}
        #<?php echo esc_attr($id); ?> .uag-diagram svg {height:115px;width:170px;overflow:visible}
        #<?php echo esc_attr($id); ?> .uag-chord-name {font-size:19px;font-weight:bold;color:#164b9b}
        #<?php echo esc_attr($id); ?> .uag-help {text-align:left;font-size:13px;line-height:1.5}
        #<?php echo esc_attr($id); ?> .uag-help p {color:#63738a}
        #<?php echo esc_attr($id); ?> .uag-arrows {display:flex;gap:8px;flex-wrap:wrap}
        #<?php echo esc_attr($id); ?> .uag-arrows span {background:#edf3fa;border-radius:6px;padding:6px;font-size:11px}
        #<?php echo esc_attr($id); ?> .uag-chord-list {padding:0 16px 15px}
        #<?php echo esc_attr($id); ?> .uag-progress {display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}
        #<?php echo esc_attr($id); ?> .uag-progress span {background:#e1e8f1;color:#52627a;padding:7px 10px;border-radius:7px;font-weight:bold;font-size:13px}
        #<?php echo esc_attr($id); ?> .uag-progress span.active {background:#164b9b;color:white;box-shadow:0 0 0 2px #ffdc54}
        #<?php echo esc_attr($id); ?> .uag-actions {display:flex;gap:8px;padding:0 16px 16px;flex-wrap:wrap}
        #<?php echo esc_attr($id); ?> .uag-actions button {border:0;border-radius:9px;padding:12px 16px;font-weight:bold}
        #<?php echo esc_attr($id); ?> .uag-play {background:#164b9b;color:white;flex:2}
        #<?php echo esc_attr($id); ?> .uag-stop {background:#dce3ed;color:#26354b;flex:1}
        #<?php echo esc_attr($id); ?> .uag-save,#<?php echo esc_attr($id); ?> .uag-load {background:#ffdc54;color:#27334a;flex:1}
        #<?php echo esc_attr($id); ?> button:disabled {opacity:.45;cursor:not-allowed}
        #<?php echo esc_attr($id); ?> .uag-message {padding:0 16px 12px;font-size:12px;color:#164b9b;min-height:20px}
        #<?php echo esc_attr($id); ?> .uag-footer {background:#e7edf5;padding:10px;text-align:center;color:#718096;font-size:10px}
        @media(max-width:600px) {
            #<?php echo esc_attr($id); ?> .uag-panel,#<?php echo esc_attr($id); ?> .uag-controls,
            #<?php echo esc_attr($id); ?> .uag-chord-area {grid-template-columns:1fr}
            #<?php echo esc_attr($id); ?> .uag-header {align-items:flex-start}
            #<?php echo esc_attr($id); ?> .uag-instruments {width:100%}
            #<?php echo esc_attr($id); ?> .uag-instrument {flex:1}
            #<?php echo esc_attr($id); ?> .uag-current {font-size:36px}
            #<?php echo esc_attr($id); ?> .uag-actions button {padding:11px 9px;font-size:12px}
        }
        </style>

        <script>
        (function(){
            const root = document.getElementById(<?php echo wp_json_encode($id); ?>);
            if (!root || root.dataset.initialized) return;
            root.dataset.initialized = '1';

            const $ = s => root.querySelector(s);
            const $$ = s => Array.from(root.querySelectorAll(s));

            const chordNames = [
                'C','Cm','C7','D','Dm','D7','E','Em','E7','F','Fm','F7',
                'G','Gm','G7','A','Am','A7','B','Bm','B7',
                'Cadd9','Dsus4','Em7','Am7','Gmaj7'
            ];

            const chordNotes = {
                C:[130.81,164.81,196,261.63,329.63],
                Cm:[130.81,155.56,196,261.63,311.13],
                C7:[130.81,164.81,196,233.08,329.63],
                D:[146.83,220,293.66,369.99,440],
                Dm:[146.83,220,293.66,349.23,440],
                D7:[146.83,220,293.66,349.23,392],
                E:[82.41,123.47,164.81,207.65,246.94,329.63],
                Em:[82.41,123.47,164.81,196,246.94,329.63],
                E7:[82.41,123.47,164.81,196,246.94,311.13],
                F:[87.31,130.81,174.61,220,261.63,349.23],
                Fm:[87.31,130.81,174.61,207.65,261.63,349.23],
                F7:[87.31,130.81,174.61,207.65,261.63,311.13],
                G:[98,146.83,196,246.94,293.66,392],
                Gm:[98,146.83,196,233.08,293.66,392],
                G7:[98,146.83,196,233.08,293.66,392],
                A:[110,164.81,220,277.18,329.63],
                Am:[110,164.81,220,261.63,329.63],
                A7:[110,164.81,220,261.63,329.63],
                B:[123.47,185,246.94,311.13,370],
                Bm:[123.47,185,246.94,293.66,370],
                B7:[123.47,185,246.94,293.66,370],
                Cadd9:[130.81,164.81,196,293.66,329.63],
                Dsus4:[146.83,220,293.66,392,440],
                Em7:[82.41,123.47,164.81,196,246.94,293.66],
                Am7:[110,164.81,220,261.63,392],
                Gmaj7:[98,146.83,196,246.94,293.66,369.99]
            };

            const chordShapes = {
                C:[-1,3,2,0,1,0], Cm:[-1,3,1,0,1,3], C7:[-1,3,2,3,1,0],
                D:[-1,-1,0,2,3,2], Dm:[-1,-1,0,2,3,1], D7:[-1,-1,0,2,1,2],
                E:[0,2,2,1,0,0], Em:[0,2,2,0,0,0], E7:[0,2,0,1,0,0],
                F:[1,3,3,2,1,1], Fm:[1,3,3,1,1,1], F7:[1,3,1,2,1,1],
                G:[3,2,0,0,0,3], Gm:[3,5,5,3,3,3], G7:[3,2,0,0,0,1],
                A:[-1,0,2,2,2,0], Am:[-1,0,2,2,1,0], A7:[-1,0,2,0,2,0],
                B:[-1,2,4,4,4,2], Bm:[-1,2,4,4,3,2], B7:[-1,2,1,2,0,2],
                Cadd9:[-1,3,2,0,3,3], Dsus4:[-1,-1,0,2,3,3],
                Em7:[0,2,0,0,0,0], Am7:[-1,0,2,0,1,0], Gmaj7:[3,2,0,0,0,2]
            };

            const styles = {
                blues: {
                    title:'Блюз',
                    description:'Блюзовий ритм із характерним акцентом. Спробуйте грати під повільний або середній темп.',
                    variants:[
                        {name:'Варіант 1 — класичний блюз',chords:['E7','A7','E7','E7','A7','A7','E7','E7','B7','A7','E7','B7'],pattern:['D','D','U','D','U','D','U','D']},
                        {name:'Варіант 2 — повільний блюз',chords:['A7','D7','A7','A7','D7','D7','A7','A7','E7','D7','A7','E7'],pattern:['D','-','D','U','-','U','D','U']},
                        {name:'Варіант 3 — блюз-рок',chords:['E7','G','A','E7','G','A','B7','A7'],pattern:['D','D','-','U','D','U','D','U']}
                    ]
                },
                rock: {
                    title:'Рок',
                    description:'Енергійний бій для рок-композицій.',
                    variants:[
                        {name:'Варіант 1 — базовий рок',chords:['E','G','D','A'],pattern:['D','D','U','D','U','D','U','U']},
                        {name:'Варіант 2 — потужний рок',chords:['Am','F','C','G'],pattern:['D','-','D','U','D','-','D','U']},
                        {name:'Варіант 3 — рок-балада',chords:['G','D','Em','C'],pattern:['D','-','U','D','-','U','D','U']}
                    ]
                },
                pop: {
                    title:'Поп',
                    description:'Універсальні акордові послідовності для популярної музики.',
                    variants:[
                        {name:'Варіант 1 — популярний',chords:['C','G','Am','F'],pattern:['D','-','D','U','-','U','D','U']},
                        {name:'Варіант 2 — легкий поп',chords:['G','D','Em','C'],pattern:['D','D','U','U','D','U','D','U']},
                        {name:'Варіант 3 — поп-балада',chords:['Am','F','C','G'],pattern:['D','-','-','U','D','-','U','-']}
                    ]
                },
                folk: {
                    title:'Фолк',
                    description:'Мелодійний акомпанемент для народних і авторських пісень.',
                    variants:[
                        {name:'Варіант 1 — простий фолк',chords:['G','C','D','G'],pattern:['D','-','D','U','D','-','D','U']},
                        {name:'Варіант 2 — народний',chords:['Am','Dm','E','Am'],pattern:['D','U','D','U','D','U','D','U']},
                        {name:'Варіант 3 — перебір',chords:['C','Am','F','G'],pattern:['P','P','P','P','P','P','P','P']}
                    ]
                },
                country: {
                    title:'Кантрі',
                    description:'Ритмічний кантрі-акомпанемент із чергуванням басових нот.',
                    variants:[
                        {name:'Варіант 1 — кантрі',chords:['G','C','D','G'],pattern:['D','-','U','D','-','U','D','U']},
                        {name:'Варіант 2 — кантрі-балада',chords:['D','G','A','D'],pattern:['P','P','D','U','P','P','D','U']}
                    ]
                },
                reggae: {
                    title:'Реггі',
                    description:'Акцент на слабких долях — легкий характерний ритм.',
                    variants:[
                        {name:'Варіант 1 — класичний реггі',chords:['Am','G','F','E'],pattern:['-','D','-','D','-','D','-','D']},
                        {name:'Варіант 2 — м'який реггі',chords:['C','G','Am','F'],pattern:['-','D','U','-','-','D','U','-']}
                    ]
                },
                jazz: {
                    title:'Джаз',
                    description:'М'який джазовий супровід із септакордами.',
                    variants:[
                        {name:'Варіант 1 — джазова основа',chords:['Am7','D7','Gmaj7','C'],pattern:['D','-','U','D','-','U','D','U']},
                        {name:'Варіант 2 — джазова прогресія',chords:['C','Am7','D7','Gmaj7'],pattern:['D','-','-','U','D','-','U','-']}
                    ]
                },
                punk: {
                    title:'Панк-рок',
                    description:'Швидкий і прямолінійний ритм.',
                    variants:[
                        {name:'Варіант 1 — швидкий панк',chords:['A','D','E','A'],pattern:['D','D','D','D','D','D','D','D']},
                        {name:'Варіант 2 — панк-рок',chords:['E','B','A','B'],pattern:['D','D','-','D','D','D','-','D']}
                    ]
                },
                ballad: {
                    title:'Балада',
                    description:'Повільний акомпанемент для ліричних композицій.',
                    variants:[
                        {name:'Варіант 1 — ніжна балада',chords:['C','G','Am','Em','F','C','F','G'],pattern:['P','P','P','P','P','P','P','P']},
                        {name:'Варіант 2 — повільний бій',chords:['G','D','Em','C'],pattern:['D','-','-','U','D','-','U','-']}
                    ]
                },
                flamenco: {
                    title:'Фламенко',
                    description:'Швидкий іспанський ритм із виразним перебором.',
                    variants:[
                        {name:'Варіант 1 — іспанський',chords:['Am','G','F','E'],pattern:['D','U','D','U','D','U','D','U']},
                        {name:'Варіант 2 — фламенко-бій',chords:['Am','G','F','E'],pattern:['D','D','U','D','U','D','U','U']}
                    ]
                }
            };

            let mode = 'styles';
            let instrument = 'acoustic';
            let sequence = [];
            let currentChords = [];
            let pattern = [];
            let chordIndex = 0;
            let tick = 0;
            let timer = null;
            let audio = null;
            let master = null;
            let playing = false;
            let bpm = 100;
            let volume = 0.65;

            const styleSelect = $('.uag-style');
            const variantSelect = $('.uag-variant');
            const chordSelect = $('.uag-chord-select');

            Object.keys(styles).forEach(key => {
                const o = document.createElement('option');
                o.value = key; o.textContent = styles[key].title;
                styleSelect.appendChild(o);
            });
            chordNames.forEach(ch => {
                const o = document.createElement('option');
                o.value = ch; o.textContent = ch;
                chordSelect.appendChild(o);
            });

            function message(text) { $('.uag-message').textContent = text; }

            function setMode(next) {
                stop();
                mode = next;
                $$('.uag-mode').forEach(b => b.classList.toggle('active',b.dataset.mode===mode));
                $('.uag-style-panel').style.display = mode==='styles'?'grid':'none';
                $('.uag-custom-panel').style.display = mode==='custom'?'grid':'none';
                updateSequence();
            }

            function loadVariants() {
                const data = styles[styleSelect.value];
                variantSelect.innerHTML = '';
                data.variants.forEach((v,i) => {
                    const o = document.createElement('option');
                    o.value = i; o.textContent = v.name;
                    variantSelect.appendChild(o);
                });
                $('.uag-description').textContent = data.description;
                updateSequence();
            }

            function updateSequence() {
                if (mode==='styles') {
                    const v = styles[styleSelect.value].variants[Number(variantSelect.value)||0];
                    currentChords = v.chords.slice();
                    pattern = v.pattern.slice();
                } else {
                    currentChords = sequence.slice();
                    const p = $('.uag-custom-pattern').value;
                    const patterns = {
                        basic:['D','-','D','-','D','-','D','-'],
                        downup:['D','U','D','U','D','U','D','U'],
                        ballad:['P','P','P','P','P','P','P','P'],
                        rock:['D','D','-','U','D','D','-','U'],
                        arpeggio:['P','P','P','P','P','P','P','P']
                    };
                    pattern = patterns[p] || patterns.basic;
                }
                chordIndex = 0;
                renderProgress();
                showChord(currentChords[0]);
            }

            function renderSequence() {
                const box = $('.uag-sequence');
                box.innerHTML = '';
                sequence.forEach((ch,i) => {
                    const item = document.createElement('span');
                    item.className = 'uag-seq-item';
                    item.append(document.createTextNode(ch));
                    const x = document.createElement('button');
                    x.type='button'; x.textContent='×'; x.title='Видалити акорд';
                    x.addEventListener('click',()=>{sequence.splice(i,1);renderSequence();updateSequence();});
                    item.appendChild(x); box.appendChild(item);
                });
            }

            function renderProgress() {
                const box = $('.uag-progress');
                box.innerHTML = '';
                currentChords.forEach((ch,i)=>{
                    const s=document.createElement('span');
                    s.textContent=ch;
                    if(i===chordIndex) s.classList.add('active');
                    box.appendChild(s);
                });
            }

            function showChord(ch) {
                $('.uag-current').textContent = ch || '—';
                $('.uag-chord-name').textContent = ch || 'Оберіть акорд';
                $('.uag-next').textContent = currentChords.length
                    ? 'Наступний акорд: '+currentChords[(chordIndex+1)%currentChords.length]
                    : 'Додайте акорди для відтворення';
                drawDiagram(ch);
                renderProgress();
            }

            function drawDiagram(ch) {
                const shape = chordShapes[ch];
                const svgNS='http://www.w3.org/2000/svg';
                const svg=document.createElementNS(svgNS,'svg');
                svg.setAttribute('viewBox','0 0 170 120');
                if(!shape) { $('.uag-diagram').innerHTML=''; return; }

                for(let f=0;f<5;f++){
                    const line=document.createElementNS(svgNS,'line');
                    line.setAttribute('x1',35+f*22);line.setAttribute('x2',35+f*22);
                    line.setAttribute('y1',15);line.setAttribute('y2',105);
                    line.setAttribute('stroke','#59677a');line.setAttribute('stroke-width',f===0?'3':'1.5');
                    svg.appendChild(line);
                }
                for(let f=0;f<5;f++){
                    const line=document.createElementNS(svgNS,'line');
                    line.setAttribute('x1',35);line.setAttribute('x2',123);
                    line.setAttribute('y1',15+f*22);line.setAttribute('y2',15+f*22);
                    line.setAttribute('stroke','#59677a');line.setAttribute('stroke-width','1.5');
                    svg.appendChild(line);
                }
                shape.forEach((f,i)=>{
                    const x=35+i*17.6;
                    const y= f>0 ? 15+(Math.min(f,4)-.5)*22 : 7;
                    const t=document.createElementNS(svgNS,'text');
                    t.setAttribute('x',x);t.setAttribute('y',y+4);
                    t.setAttribute('text-anchor','middle');t.setAttribute('font-size','12');
                    t.setAttribute('fill',f<0?'#c0392b':'#164b9b');
                    t.textContent=f<0?'×':f===0?'○':'';
                    svg.appendChild(t);
                    if(f>0){
                        const c=document.createElementNS(svgNS,'circle');
                        c.setAttribute('cx',x);c.setAttribute('cy',y);c.setAttribute('r','6');
                        c.setAttribute('fill','#164b9b');svg.appendChild(c);
                    }
                });
                $('.uag-diagram').replaceChildren(svg);
            }

            function ensureAudio() {
                if (!audio) {
                    const AC=window.AudioContext||window.webkitAudioContext;
                    if(!AC) {message('Ваш браузер не підтримує Web Audio API.');return false;}
                    audio=new AC();
                    master=audio.createGain();
                    master.gain.value=volume;
                    master.connect(audio.destination);
                }
                if(audio.state==='suspended') audio.resume();
                return true;
            }

            function pluck(freq,when,duration) {
                if(!audio||!master) return;
                const gain=audio.createGain();
                const filter=audio.createBiquadFilter();
                const osc=audio.createOscillator();
                const osc2=audio.createOscillator();
                const peak=instrument==='electric'?0.18:0.25;
                const decay=instrument==='electric'?1.1:0.65;

                osc.type=instrument==='electric'?'sawtooth':'triangle';
                osc.frequency.setValueAtTime(freq,when);
                osc2.type='sine';osc2.frequency.setValueAtTime(freq*2.01,when);
                const g2=audio.createGain();g2.gain.value=instrument==='electric'?0.10:0.18;
                filter.type='lowpass';
                filter.frequency.setValueAtTime(instrument==='electric'?2600:4200,when);
                gain.gain.setValueAtTime(0.0001,when);
                gain.gain.exponentialRampToValueAtTime(peak,when+0.012);
                gain.gain.exponentialRampToValueAtTime(0.0001,when+decay);
                osc.connect(filter);osc2.connect(g2);g2.connect(filter);
                filter.connect(gain);gain.connect(master);
                osc.start(when);osc2.start(when);
                osc.stop(when+decay+0.05);osc2.stop(when+decay+0.05);

                // Короткий шумовий імпульс імітує атаку струни.
                const length=Math.floor(audio.sampleRate*0.035);
                const buffer=audio.createBuffer(1,length,audio.sampleRate);
                const data=buffer.getChannelData(0);
                for(let i=0;i<length;i++) data[i]=(Math.random()*2-1)*(1-i/length);
                const noise=audio.createBufferSource();
                const ng=audio.createGain();
                const nf=audio.createBiquadFilter();
                nf.type='bandpass';nf.frequency.value=Math.min(7000,freq*8);
                ng.gain.setValueAtTime(instrument==='electric'?0.025:0.045,when);
                ng.gain.exponentialRampToValueAtTime(0.0001,when+0.035);
                noise.buffer=buffer;noise.connect(nf);nf.connect(ng);ng.connect(master);
                noise.start(when);noise.stop(when+0.04);
            }

            function playChord(direction) {
                const ch=currentChords[chordIndex];
                if(!ch) return;
                const notes=(chordNotes[ch]||chordNotes.C).slice();
                if(direction==='U') notes.reverse();
                if(direction==='P') {
                    const order=[0,3,2,1,2,3];
                    order.forEach((n,i)=>{
                        if(notes[n]) pluck(notes[n],audio.currentTime+i*0.075,0.3);
                    });
                } else {
                    notes.forEach((freq,i)=>pluck(freq,audio.currentTime+i*0.018,0.45));
                }
                const stage=$('.uag-stage');
                stage.classList.remove('uag-down','uag-up');
                void stage.offsetWidth;
                stage.classList.add(direction==='U'?'uag-up':'uag-down');
                $('.uag-direction').textContent=direction==='U'?'↑ Рух руки вгору':direction==='P'?'♪ Перебір пальцями':'↓ Рух руки вниз';
            }

            function tickStep() {
                if(!playing) return;
                const step=tick%8;
                const direction=pattern[step]||'-';
                if(direction!=='-') playChord(direction);
                const beat=Math.floor(step/2);
                $('.uag-beat').textContent=[0,1,2,3].map((n)=>n===beat?'●':'○').join(' ');
                tick++;
                if(tick%8===0) {
                    chordIndex=(chordIndex+1)%currentChords.length;
                    showChord(currentChords[chordIndex]);
                }
            }

            function start() {
                if(playing) return;
                if(!currentChords.length) {message('Спочатку додайте акорди або оберіть стиль.');return;}
                if(!ensureAudio()) return;
                playing=true;tick=0;chordIndex=0;showChord(currentChords[0]);
                bpm=Number($('.uag-bpm').value);
                master.gain.setTargetAtTime(volume,audio.currentTime,0.03);
                $('.uag-play').disabled=true;$('.uag-stop').disabled=false;
                $('.uag-direction').textContent='Відтворення...';
                tickStep();
                timer=setInterval(tickStep,60000/bpm/2);
                message('Музика грає. Можна змінювати гучність та швидкість після зупинки.');
            }

            function stop() {
                playing=false;
                if(timer) clearInterval(timer);
                timer=null;
                $('.uag-play').disabled=false;$('.uag-stop').disabled=true;
                $('.uag-stage').classList.remove('uag-down','uag-up');
                $('.uag-direction').textContent='Відтворення зупинено';
                $('.uag-beat').textContent='● ● ● ●';
            }

            function saveComposition() {
                if(!sequence.length) {message('Спочатку створи власну послідовність акордів.');return;}
                const name=prompt('Назва композиції:','Моя композиція');
                if(!name) return;
                let saved=[];
                try {saved=JSON.parse(localStorage.getItem('uag_saved')||'[]');} catch(e){}
                saved.push({name:name,chords:sequence,pattern:$('.uag-custom-pattern').value,bpm:Number($('.uag-bpm').value)});
                localStorage.setItem('uag_saved',JSON.stringify(saved));
                message('Композицію «'+name+'» збережено на цьому пристрої.');
            }

            function loadComposition() {
                let saved=[];
                try {saved=JSON.parse(localStorage.getItem('uag_saved')||'[]');} catch(e){}
                if(!saved.length) {message('Збережених композицій поки немає.');return;}
                const menu=saved.map((x,i)=>(i+1)+'. '+x.name+' ('+x.chords.join(' – ')+')').join('\n');
                const answer=prompt('Введіть номер композиції:\n'+menu,'1');
                const item=saved[Number(answer)-1];
                if(!item) return;
                stop();sequence=item.chords.slice();
                $('.uag-custom-pattern').value=item.pattern||'basic';
                $('.uag-bpm').value=item.bpm||100;
                $('.uag-bpm-label').textContent=$('.uag-bpm').value+' BPM';
                renderSequence();setMode('custom');updateSequence();
                message('Завантажено: '+item.name);
            }

            $$('.uag-mode').forEach(b=>b.addEventListener('click',()=>setMode(b.dataset.mode)));
            $$('.uag-instrument').forEach(b=>b.addEventListener('click',()=>{
                instrument=b.dataset.instrument;
                $$('.uag-instrument').forEach(x=>x.classList.toggle('active',x===b));
                message(instrument==='acoustic'?'Обрана акустична гітара':'Обрана електрична гітара');
            }));
            styleSelect.addEventListener('change',loadVariants);
            variantSelect.addEventListener('change',updateSequence);
            $('.uag-custom-pattern').addEventListener('change',updateSequence);
            $('.uag-add').addEventListener('click',()=>{
                if(sequence.length>=32) {message('Максимум 32 акорди в одній послідовності.');return;}
                sequence.push(chordSelect.value);renderSequence();updateSequence();
            });
            $('.uag-clear').addEventListener('click',()=>{sequence=[];renderSequence();updateSequence();});
            $('.uag-play').addEventListener('click',start);
            $('.uag-stop').addEventListener('click',stop);
            $('.uag-save').addEventListener('click',saveComposition);
            $('.uag-load').addEventListener('click',loadComposition);
            $('.uag-bpm').addEventListener('input',e=>{
                bpm=Number(e.target.value);
                $('.uag-bpm-label').textContent=bpm+' BPM';
                if(playing) {stop();start();}
            });
            $('.uag-volume').addEventListener('input',e=>{
                volume=Number(e.target.value)/100;
                if(master&&audio) master.gain.setTargetAtTime(volume,audio.currentTime,0.04);
            });

            loadVariants();
            renderSequence();
            message('Оберіть стиль або створіть власну послідовність акордів.');
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

new UA_Guitar_Trainer();
