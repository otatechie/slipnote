<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Workspace;
use App\Support\SimplePdf;
use App\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Makes the example board /demo opens: a class with four subjects and real,
 * readable files, uploads locked so visitors can only look. Run it once on
 * the live site, then set DEMO_BOARD to the slug it prints.
 */
class ExampleBoard extends Command
{
    protected $signature = 'slipnote:example-board';

    protected $description = 'Create the example board that slipnote.co/demo opens';

    /** [code, title, [[section, title, days ago, lines], ...]] */
    private const COURSES = [
        ['Maths', 'Algebra, graphs and statistics', [
            ['notes', 'Week 1: Expanding and factorising', 20, [
                'Expanding means multiplying out brackets. Factorising is the reverse: putting them back.',
                '',
                'Expand: 3(x + 4) = 3x + 12',
                'Expand two brackets: (x + 2)(x + 5) = x^2 + 7x + 10',
                'Factorise: 6x + 9 = 3(2x + 3)',
                'Factorise a quadratic: x^2 + 5x + 6 = (x + 2)(x + 3)',
                '',
                'Check your answer by expanding it again. You should get back to where you started.',
            ]],
            ['notes', 'Week 2: Straight-line graphs', 13, [
                'Every straight line can be written as y = mx + c.',
                'm is the gradient (how steep the line is). c is where it crosses the y-axis.',
                '',
                'Gradient = change in y / change in x between any two points on the line.',
                'Parallel lines have the same gradient.',
                '',
                'Example: the line through (1, 3) and (3, 7) has gradient (7 - 3) / (3 - 1) = 2,',
                'so its equation is y = 2x + 1.',
            ]],
            ['slides', 'Simultaneous equations (lesson slides)', 9, [
                'Two equations, two unknowns: find the values that make both true.',
                '',
                'Elimination: make one variable cancel by adding or subtracting the equations.',
                'Substitution: rearrange one equation and put it into the other.',
                '',
                'Example: x + y = 10 and x - y = 2. Adding them gives 2x = 12, so x = 6 and y = 4.',
            ]],
            ['past_papers', 'Practice questions: algebra', 4, [
                '1. Expand and simplify 2(3x - 1) + 4(x + 2).',
                '2. Factorise fully 12x^2 - 8x.',
                '3. Factorise x^2 - 9.',
                '4. Find the gradient of the line through (2, 5) and (6, 13).',
                '5. Solve the simultaneous equations 2x + y = 11 and x - y = 1.',
                '',
                'Answers are in next week\'s notes.',
            ]],
        ]],
        ['English', 'Reading, writing and set texts', [
            ['notes', 'Planning a persuasive essay', 18, [
                'A good persuasive essay has one clear opinion, and every paragraph supports it.',
                '',
                'Introduction: hook the reader and state your view.',
                'Body: one point per paragraph, each with evidence and an explanation.',
                'Counter-argument: show you understand the other side, then answer it.',
                'Conclusion: return to your view and end with something memorable.',
                '',
                'Useful techniques: rhetorical questions, the rule of three, direct address.',
            ]],
            ['notes', 'Poetry: comparing two poems', 11, [
                'Compare, don\'t describe: every paragraph should mention both poems.',
                '',
                'Look at: theme, speaker, structure, imagery, language and tone.',
                'Linking words: similarly, whereas, in contrast, both poets.',
                '',
                'Quote briefly, then explain the effect of the words the poet chose.',
            ]],
            ['slides', 'Analysing a newspaper article (lesson slides)', 6, [
                'Who wrote it, for whom, and why? Audience and purpose shape every choice.',
                '',
                'Headline: how does it grab attention?',
                'Language: emotive words, statistics, expert quotes.',
                'Structure: what comes first, and what is left until the end?',
            ]],
            ['past_papers', 'Practice questions: close reading', 2, [
                'Read the article provided in class, then answer:',
                '',
                '1. In your own words, give two reasons the writer gives for the change.',
                '2. Explain how one example of word choice shows the writer\'s attitude.',
                '3. How effective is the final paragraph as a conclusion? Give reasons.',
            ]],
        ]],
        ['Biology', 'Cells, genetics and ecosystems', [
            ['notes', 'Cell structure', 21, [
                'All living things are made of cells.',
                '',
                'Animal and plant cells both have a nucleus, cytoplasm, cell membrane and mitochondria.',
                'Plant cells also have a cell wall, chloroplasts and a large vacuole.',
                '',
                'Nucleus: holds the genetic information and controls the cell.',
                'Mitochondria: where aerobic respiration releases energy.',
                'Chloroplasts: where photosynthesis happens.',
            ]],
            ['notes', 'Photosynthesis', 12, [
                'carbon dioxide + water -> glucose + oxygen (light energy is needed)',
                '',
                'It happens in the chloroplasts, which contain the green pigment chlorophyll.',
                'Limiting factors: light intensity, carbon dioxide concentration and temperature.',
                '',
                'The glucose is used for respiration, or stored as starch.',
            ]],
            ['slides', 'DNA and inheritance (lesson slides)', 7, [
                'DNA is a double helix. A gene is a section of DNA that codes for a protein.',
                '',
                'Alleles are different forms of the same gene.',
                'Dominant alleles show their effect even if only one copy is present.',
                'Recessive alleles only show their effect when two copies are present.',
            ]],
            ['past_papers', 'Practice questions: cells', 3, [
                '1. Name two structures found in plant cells but not animal cells.',
                '2. State the function of the mitochondria.',
                '3. Write the word equation for photosynthesis.',
                '4. Give one factor that could limit the rate of photosynthesis.',
            ]],
        ]],
        ['History', 'The twentieth century', [
            ['notes', 'Causes of the First World War', 19, [
                'Historians often use MAIN to remember the long-term causes:',
                '',
                'Militarism: an arms race, especially between Britain and Germany at sea.',
                'Alliances: Europe split into two armed camps.',
                'Imperialism: rivalry over colonies and trade.',
                'Nationalism: pride and tension, especially in the Balkans.',
                '',
                'The short-term trigger: the assassination of Archduke Franz Ferdinand in June 1914.',
            ]],
            ['slides', 'The road to the Second World War (lesson slides)', 8, [
                'The Treaty of Versailles left resentment in Germany.',
                'The Great Depression helped extremist parties win support.',
                'Appeasement: Britain and France gave in to demands to avoid war.',
                '',
                'September 1939: Germany invades Poland; Britain and France declare war.',
            ]],
            ['past_papers', 'Practice questions: source analysis', 1, [
                'Look at the two sources handed out in class.',
                '',
                '1. What can you learn from Source A about life on the home front?',
                '2. How useful is Source B for understanding why people supported appeasement?',
                '3. Compare the views in Source A and Source B.',
            ]],
        ]],
    ];

    public function handle(): int
    {
        [$board, $secret] = Workspace::provision('Example class: Year 11');
        // Visitors can look and download, but only the owner adds files.
        $board->forceFill(['uploads_owner_only' => true])->save();
        app(Tenancy::class)->set($board);

        $files = 0;
        foreach (self::COURSES as $position => [$code, $title, $materials]) {
            $course = Course::create(['code' => $code, 'title' => $title, 'slug' => Str::slug($code), 'position' => $position]);
            foreach ($materials as [$section, $name, $daysAgo, $lines]) {
                $bytes = SimplePdf::make($name, $lines);
                $path = 'materials/example-'.Str::random(24).'.pdf';
                if (! Storage::disk('local')->put($path, $bytes)) {
                    $this->error("Couldn't save a file to storage. Nothing more was added; delete the board from the operator dashboard and try again.");

                    return self::FAILURE;
                }
                $material = $course->materials()->create([
                    'section' => $section,
                    'title' => $name,
                    'original_filename' => Str::slug($name).'.pdf',
                    'stored_path' => $path,
                    'manage_token' => Str::random(40),
                    'download_token' => Str::random(40),
                    'file_size' => strlen($bytes),
                    'content_hash' => hash('sha256', $bytes),
                    'by_owner' => true,
                ]);
                $material->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();
                $files++;
            }
        }

        $this->info("Made \"{$board->name}\": ".count(self::COURSES)." courses, {$files} files, uploads locked.");
        $this->newLine();
        $this->line('Board:       '.route('courses.index', ['workspace' => $board->slug]));
        $this->line('Owner link:  '.route('courses.index', ['workspace' => $board->slug, 'owner' => $secret]));
        $this->line('             Shown once. Keep it private: it lets you edit or delete the board.');
        $this->newLine();
        $this->line("Now set DEMO_BOARD={$board->slug} in the environment and redeploy, so /demo opens it.");

        return self::SUCCESS;
    }
}
