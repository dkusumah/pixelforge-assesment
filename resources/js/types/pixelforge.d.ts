export type GenerationStatus = 'queued' | 'processing' | 'succeeded' | 'failed';
export type GenerationSize = '1024x1024' | '1536x1024' | '1024x1536';
export type GenerationQuality = 'low' | 'medium' | 'high';

export interface Generation {
    id: number;
    user_id: number;
    pixelforge_id?: string | null;
    idempotency_key: string;
    status: GenerationStatus;
    prompt: string;
    size: GenerationSize;
    quality: GenerationQuality;
    reference_images?: string[] | null;
    metadata?: Record<string, string> | null;
    image_url?: string | null;
    image_url_expires_at?: string | null;
    estimated_cost: string;
    actual_cost?: string | null;
    provider_cost?: string | null;
    rates?: {
        input_text_tokens: string;
        input_image_tokens: string;
        output_tokens: string;
        reasoning_tokens: string;
    } | null;
    usage?: {
        input_text_tokens: number;
        input_image_tokens: number;
        output_tokens: number;
        reasoning_tokens: number;
        total_tokens: number;
    } | null;
    error_code?: string | null;
    error_message?: string | null;
    started_at?: string | null;
    completed_at?: string | null;
    created_at: string;
    updated_at: string;
}

export interface CostEstimate {
    estimated_tokens: {
        input_text: { min: number; expected: number; max: number };
        input_image: number;
        output: number;
        reasoning: { min: number; max: number };
    };
    estimated_provider_cost: { min: string; expected: string; max: string };
    estimated_user_cost: { min: string; expected: string; max: string };
    rates: Record<string, string>;
}

export interface TestingKeywordItem {
    keyword: string;
    label: string;
    description: string;
}

export interface DailyStat {
    date: string;
    generations_count: number;
    user_spend: string;
    provider_cost: string;
    profit: string;
}

export interface UserStat {
    user_id: number;
    user_name: string;
    user_email: string;
    generations_count: number;
    user_spend: string;
    provider_cost: string;
    profit: string;
}

export interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLinks[];
}
