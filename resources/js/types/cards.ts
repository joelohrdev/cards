export type Option = {
    value: string;
    label: string;
};

export type TagSummary = {
    id: number;
    name: string;
    color: string;
};

export type LocationOption = {
    id: number;
    name: string;
    path: string;
    depth: number;
    parent_id: number | null;
    type: string;
};

export type FormOptions = {
    sports: Option[];
    graders: Option[];
    conditions: Option[];
    statuses: Option[];
    platforms: Option[];
    locationTypes: Option[];
    tags: TagSummary[];
    locations: LocationOption[];
};

export type CardSetSummary = {
    id: number;
    full_name: string;
    name: string;
    year: number;
    season: string | null;
    sport: string;
    sport_label: string;
    manufacturer: string | null;
    card_count: number | null;
};

export type ParallelSummary = {
    id: number;
    name: string;
    print_run: number | null;
};

export type CardSummary = {
    id: number;
    number: string;
    name: string;
    subset: string | null;
    is_rookie: boolean;
    is_autograph: boolean;
    is_memorabilia: boolean;
    is_short_print: boolean;
    print_run: number | null;
    team?: string | null;
    players?: { id: number; name: string }[];
    set?: CardSetSummary;
    parallels?: ParallelSummary[];
};

export type CollectionItem = {
    id: number;
    title: string;
    card: CardSummary & { set: CardSetSummary };
    parallel: ParallelSummary | null;
    print_run: number | null;
    serial_number: number | null;
    is_graded: boolean;
    grader: string | null;
    grade: string | null;
    cert_number: string | null;
    raw_condition: string | null;
    condition_label: string | null;
    status: string;
    status_label: string;
    purchase_price: number | null;
    purchase_date: string | null;
    purchase_source: string | null;
    estimated_value: number | null;
    value_updated_at: string | null;
    location: { id: number; name: string; path: string } | null;
    storage_position: string | null;
    notes: string | null;
    tags?: TagSummary[];
    image?: string | null;
    images?: { id: number; url: string; side: string }[];
    created_at: string;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
    next_page_url: string | null;
    prev_page_url: string | null;
};

/**
 * Result of looking up a graded card's certification number with the grading company.
 */
export type CertLookupResult = {
    grader: string;
    cert_number: string;
    grade: string | null;
    year: number | null;
    brand: string | null;
    set_name: string | null;
    card_number: string | null;
    player: string | null;
    variety: string | null;
    sport: string | null;
};

/**
 * A card identified from a photo, plus any catalog cards that look like matches.
 */
export type PhotoSuggestion = {
    sport: string | null;
    year: number | null;
    manufacturer: string | null;
    set_name: string | null;
    number: string | null;
    name: string | null;
    team: string | null;
    parallel: string | null;
    serial_number: number | null;
    print_run: number | null;
    is_rookie: boolean;
    is_autograph: boolean;
    is_memorabilia: boolean;
    is_graded: boolean;
    grader: string | null;
    grade: string | null;
    cert_number: string | null;
    confidence: 'high' | 'medium' | 'low';
    matches: (CardSummary & {
        set: CardSetSummary;
        parallels: ParallelSummary[];
    })[];
};
