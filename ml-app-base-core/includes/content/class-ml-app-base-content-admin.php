<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ML_App_Base_Content_Admin {
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_post_ml_app_base_content_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_ml_app_base_content_clear_cache', array( $this, 'clear_cache' ) );
    }

    public function add_plugin_admin_menu() {
        add_menu_page(
            'ML App Base Content',
            'ML App Base Content',
            'manage_options',
            'ml-app-base-core',
            array( $this, 'display_plugin_setup_page' ),
            'dashicons-rest-api',
            31
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_ml-app-base-core' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'ml-app-base-core-admin', ML_APP_BASE_CONTENT_URL . 'assets/admin.css', array(), ML_APP_BASE_CONTENT_VERSION, 'all' );
        wp_enqueue_script( 'ml-app-base-core-admin', ML_APP_BASE_CONTENT_URL . 'assets/admin.js', array(), ML_APP_BASE_CONTENT_VERSION, true );
    }

    public function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sem permissão.', 'ml-app-base-core' ) );
        }

        check_admin_referer( 'ml_app_base_content_save_settings' );
        ML_App_Base_Content_Settings::update( $_POST );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'    => 'ml-app-base-core',
                    'updated' => '1',
                    'tab'     => isset( $_POST['active_tab'] ) ? sanitize_key( wp_unslash( $_POST['active_tab'] ) ) : 'home',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function clear_cache() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sem permissão.', 'ml-app-base-core' ) );
        }

        check_admin_referer( 'ml_app_base_content_clear_cache' );
        ML_App_Base_Content_Settings::clear_cache();

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'  => 'ml-app-base-core',
                    'cache' => 'cleared',
                    'tab'   => 'status',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function display_plugin_setup_page() {
        $settings         = ML_App_Base_Content_Settings::all();
        $controls         = ML_App_Base_Content_Settings::home_controls();
        $ml_banner_active = post_type_exists( 'mlcp_item' );
        $news_categories  = $this->get_news_categories_for_selector();
        $active_tab       = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'home';
        $valid_tabs       = array( 'home', 'news', 'events', 'banners', 'photos', 'about', 'status' );
        if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
            $active_tab = 'home';
        }

        $toast_message = '';
        $toast_type    = 'success';
        if ( isset( $_GET['updated'] ) ) {
            $toast_message = 'Configurações salvas.';
        } elseif ( isset( $_GET['cache'] ) ) {
            $toast_message = 'Cache da API limpo.';
        }

        $logo_url = ML_APP_BASE_CONTENT_URL . 'assets/images/logo-wordpress.png';
        ?>
        <div class="wrap mlpb-admin-wrap mlab-content-admin" data-toast-message="<?php echo esc_attr( $toast_message ); ?>" data-toast-type="<?php echo esc_attr( $toast_type ); ?>">
            <div id="mlpb-toast-area" aria-live="polite" aria-atomic="true"></div>

            <div class="mlpb-hero">
                <div class="mlpb-hero-brand">
                    <div class="mlpb-hero-mark">
                        <img src="<?php echo esc_url( $logo_url ); ?>" alt="ML Lopes Design" />
                    </div>
                    <div class="mlpb-hero-copy">
                        <span class="mlpb-hero-eyebrow">ML Lopes Design · App App Base</span>
                        <h1>ML App Base Content</h1>
                        <p class="mlpb-intro">Painel de conteúdo público do aplicativo. Controle a Home, Notícias, Próximos Eventos, Banners, Sobre e endpoints sem mexer no app.</p>
                    </div>
                </div>
                <div class="mlpb-hero-meta">
                    <span class="mlpb-version-badge">v<?php echo esc_html( ML_APP_BASE_CONTENT_VERSION ); ?></span>
                    <div class="mlpb-hero-tags">
                        <span class="mlpb-chip">Slug: ml-app-base-core</span>
                        <span class="mlpb-chip">REST: ml-app-base/v1</span>
                        <span class="mlpb-chip <?php echo $ml_banner_active ? '' : 'is-neutral'; ?>">ML Banner Pro: <?php echo $ml_banner_active ? 'Ativo' : 'Não detectado'; ?></span>
                    </div>
                </div>
            </div>

            <div class="mlpb-summary-grid">
                <div class="mlpb-summary-card"><span class="mlpb-summary-label">Notícias na Home</span><strong><?php echo ! empty( $controls['news']['enabled'] ) ? 'Ativas' : 'Ocultas'; ?></strong><small><?php echo esc_html( (string) $controls['news']['limit'] ); ?> item(ns) · <?php echo esc_html( $controls['news']['title'] ); ?></small></div>
                <div class="mlpb-summary-card"><span class="mlpb-summary-label">Próximos Eventos</span><strong>ML Banner Pro</strong><small><?php echo esc_html( (string) $controls['events']['limit'] ); ?> banner(s) · <?php echo ! empty( $controls['events']['banner_only'] ) ? 'somente banner' : 'com texto'; ?></small></div>
                <div class="mlpb-summary-card"><span class="mlpb-summary-label">Banners</span><strong><?php echo ! empty( $controls['banners']['enabled'] ) ? 'Ativos' : 'Ocultos'; ?></strong><small>Fonte: ML Banner Pro</small></div>
                <div class="mlpb-summary-card"><span class="mlpb-summary-label">Álbuns de Fotos</span><strong><?php echo ! empty( $controls['photo_albums']['enabled'] ) ? 'Ativos' : 'Ocultos'; ?></strong><small><?php echo esc_html( (string) $controls['photo_albums']['home_limit'] ); ?> na Home · <?php echo esc_html( (string) $controls['photo_albums']['screen_limit'] ); ?> na tela Fotos</small></div>
            </div>

            <nav class="mlpb-tab-nav" aria-label="Abas do ML App Base Content">
                <?php $this->tab_button( 'home', 'Home / Cabeçalho', $active_tab ); ?>
                <?php $this->tab_button( 'news', 'Notícias', $active_tab ); ?>
                <?php $this->tab_button( 'events', 'Próximos Eventos', $active_tab ); ?>
                <?php $this->tab_button( 'banners', 'Banners', $active_tab ); ?>
                <?php $this->tab_button( 'photos', 'Álbuns de Fotos', $active_tab ); ?>
                <?php $this->tab_button( 'about', 'Sobre', $active_tab ); ?>
                <?php $this->tab_button( 'status', 'Status / Endpoints', $active_tab ); ?>
            </nav>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'ml_app_base_content_save_settings' ); ?>
                <input type="hidden" name="action" value="ml_app_base_content_save_settings" />
                <input type="hidden" name="active_tab" id="mlab-active-tab" value="<?php echo esc_attr( $active_tab ); ?>" />

                <section id="mlpb-tab-home" class="mlpb-tab-panel <?php echo 'home' === $active_tab ? 'is-active' : ''; ?>" <?php echo 'home' === $active_tab ? '' : 'hidden'; ?>>
                    <div class="mlpb-grid mlpb-grid-2">
                        <div class="mlpb-card">
                            <div class="mlpb-card-header"><div><h2>Cabeçalho da Home</h2><p class="mlpb-muted">Controle texto, imagem e cores do cabeçalho consumido pelo app.</p></div></div>
                            <div class="mlpb-form-grid">
                                <?php $this->checkbox( 'home_header_enabled', 'Exibir cabeçalho no app', $settings ); ?>
                                <?php $this->field( 'text', 'home_header_title', 'Texto principal', $settings ); ?>
                                <?php $this->field( 'text', 'home_header_subtitle', 'Subtítulo', $settings ); ?>
                                <?php $this->field( 'url', 'home_header_logo', 'Logo/Imagem URL', $settings ); ?>
                                <?php $this->field( 'text', 'home_header_logo_size', 'Tamanho da logo', $settings, 'small, medium ou large' ); ?>
                                <?php $this->field( 'text', 'home_header_background_color', 'Cor de fundo', $settings, '#ffffff' ); ?>
                                <?php $this->field( 'text', 'home_header_text_color', 'Cor do texto', $settings, '#000000' ); ?>
                            </div>
                        </div>
                        <div class="mlpb-card">
                            <div class="mlpb-card-header"><div><h2>Governança da Home</h2><p class="mlpb-muted">O app deve obedecer <code>/home.home_controls</code>.</p></div></div>
                            <ul class="mlpb-list">
                                <li>Notícias: quantidade, imagem, resumo, data, link e botão.</li>
                                <li>Eventos: quantidade, somente banner, favoritos, link e ampliar imagem.</li>
                                <li>Banners: fonte ML Banner Pro, sem categoria de posts.</li>
                                <li>Álbuns de Fotos: quantidade na Home, quantidade na tela Fotos, título, data e botão.</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section id="mlpb-tab-news" class="mlpb-tab-panel <?php echo 'news' === $active_tab ? 'is-active' : ''; ?>" <?php echo 'news' === $active_tab ? '' : 'hidden'; ?>>
                    <div class="mlpb-card">
                        <div class="mlpb-card-header"><div><h2>Últimas Notícias</h2><p class="mlpb-muted">Controle quantas notícias aparecem na Home e quais elementos o app deve exibir.</p></div></div>
                        <div class="mlpb-form-grid">
                            <?php $this->checkbox( 'home_news_enabled', 'Exibir seção de notícias na Home', $settings ); ?>
                            <?php $this->field( 'text', 'home_news_title', 'Título da seção', $settings ); ?>
                            <?php $this->field( 'number', 'home_news_limit', 'Quantidade de notícias', $settings ); ?>
                            <?php $this->checkbox( 'home_news_show_image', 'Mostrar imagem destacada', $settings ); ?>
                            <?php $this->checkbox( 'home_news_show_excerpt', 'Mostrar resumo', $settings ); ?>
                            <?php $this->checkbox( 'home_news_show_date', 'Mostrar data', $settings ); ?>
                            <?php $this->checkbox( 'home_news_show_read_more', 'Mostrar botão Ver/Ler notícia', $settings ); ?>
                            <?php $this->checkbox( 'home_news_open_in_site', 'Abrir notícia no site ao clicar', $settings ); ?>
                        </div>
                        <?php $this->news_category_selector( $settings, $news_categories ); ?>
                        <div class="mlpb-note" style="margin-top:16px;"><strong>Fonte:</strong> posts nas categorias selecionadas acima. A imagem vem da imagem destacada do post e a ordenação segue a data de publicação mais recente.</div>
                    </div>
                </section>

                <section id="mlpb-tab-events" class="mlpb-tab-panel <?php echo 'events' === $active_tab ? 'is-active' : ''; ?>" <?php echo 'events' === $active_tab ? '' : 'hidden'; ?>>
                    <div class="mlpb-grid mlpb-grid-2">
                        <div class="mlpb-card">
                            <div class="mlpb-card-header"><div><h2>Próximos Eventos</h2><p class="mlpb-muted">Agora os eventos do app vêm dos banners ativos do ML Banner Pro.</p></div></div>
                            <div class="mlpb-note"><strong>Regra ativa:</strong> imagem e link vêm do ML Banner Pro. A categoria <code>proximos-eventos</code> não é mais fonte desta seção.</div>
                            <div class="mlpb-form-grid" style="margin-top:16px;">
                                <?php $this->checkbox( 'home_events_enabled', 'Exibir Próximos Eventos na Home', $settings ); ?>
                                <?php $this->field( 'text', 'home_events_title', 'Título da seção', $settings ); ?>
                                <?php $this->field( 'number', 'home_events_limit', 'Quantidade de banners/eventos', $settings ); ?>
                                <?php $this->checkbox( 'home_events_banner_only', 'Mostrar somente banner', $settings ); ?>
                                <?php $this->checkbox( 'home_events_show_favorite', 'Mostrar adicionar aos favoritos', $settings ); ?>
                                <?php $this->checkbox( 'home_events_open_link', 'Abrir link do banner ao clicar', $settings ); ?>
                                <?php $this->checkbox( 'home_events_fallback_expand', 'Ampliar imagem quando não houver link', $settings ); ?>
                                <?php $this->field( 'text', 'ml_banner_group', 'Grupo do ML Banner Pro para o app (slug, opcional)', $settings ); ?>
                            </div>
                        </div>
                        <div class="mlpb-card">
                            <div class="mlpb-card-header"><div><h2>Campos usados</h2><p class="mlpb-muted">A API normaliza os itens do ML Banner Pro no formato esperado pelo app.</p></div></div>
                            <ul class="mlpb-list">
                                <li><code>image.url</code> vem da imagem do banner.</li>
                                <li><code>link</code>, <code>permalink</code> e <code>target_url</code> vêm do link do banner.</li>
                                <li><code>source</code> retorna <code>ml_banner_pro</code>.</li>
                                <li><code>home_controls.events</code> informa como o app deve renderizar.</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section id="mlpb-tab-banners" class="mlpb-tab-panel <?php echo 'banners' === $active_tab ? 'is-active' : ''; ?>" <?php echo 'banners' === $active_tab ? '' : 'hidden'; ?>>
                    <div class="mlpb-card">
                        <div class="mlpb-card-header"><div><h2>Banners</h2><p class="mlpb-muted">Controle geral dos banners retornados em <code>/banners</code> e na Home.</p></div></div>
                        <div class="mlpb-form-grid">
                            <?php $this->checkbox( 'home_banners_enabled', 'Exibir banners na Home', $settings ); ?>
                            <?php $this->field( 'text', 'home_banners_title', 'Título da seção', $settings ); ?>
                            <?php $this->field( 'number', 'home_banners_limit', 'Quantidade de banners', $settings ); ?>
                        </div>
                        <div class="mlpb-note" style="margin-top:16px;"><strong>Fonte:</strong> ML Banner Pro. Este plugin não cadastra banners; ele lê imagens e links já cadastrados lá.</div>
                    </div>
                </section>

                <section id="mlpb-tab-photos" class="mlpb-tab-panel <?php echo 'photos' === $active_tab ? 'is-active' : ''; ?>" <?php echo 'photos' === $active_tab ? '' : 'hidden'; ?>>
                    <div class="mlpb-grid mlpb-grid-2">
                        <div class="mlpb-card">
                            <div class="mlpb-card-header"><div><h2>Álbuns de Fotos</h2><p class="mlpb-muted">Controle a seção de fotos consumida pelo app a partir do ML Gallery Pro.</p></div></div>
                            <div class="mlpb-form-grid">
                                <?php $this->checkbox( 'home_photo_albums_enabled', 'Exibir Álbuns de Fotos na Home', $settings ); ?>
                                <?php $this->field( 'text', 'home_photo_albums_title', 'Título da seção na Home', $settings ); ?>
                                <?php $this->field( 'number', 'home_photo_albums_limit', 'Quantidade na Home', $settings ); ?>
                                <?php $this->field( 'text', 'photos_screen_title', 'Título da tela Fotos', $settings ); ?>
                                <?php $this->field( 'number', 'photos_screen_limit', 'Quantidade na tela Fotos', $settings ); ?>
                                <?php $this->checkbox( 'home_photo_albums_show_title', 'Mostrar título do álbum', $settings ); ?>
                                <?php $this->checkbox( 'home_photo_albums_show_date', 'Mostrar data do álbum', $settings ); ?>
                                <?php $this->checkbox( 'home_photo_albums_show_button', 'Mostrar botão Ver fotos', $settings ); ?>
                                <?php $this->field( 'text', 'home_photo_albums_button_label', 'Texto do botão', $settings ); ?>
                            </div>
                        </div>
                        <div class="mlpb-card">
                            <div class="mlpb-card-header"><div><h2>Como o app usa</h2><p class="mlpb-muted">A Home deve colocar este carrossel entre Próximos Eventos e Últimas Notícias.</p></div></div>
                            <ul class="mlpb-list">
                                <li><code>/photo-albums</code> entrega os álbuns do ML Gallery Pro.</li>
                                <li><code>/home.photo_albums</code> entrega os álbuns para a Home.</li>
                                <li><code>/home.home_controls.photo_albums</code> entrega limites, título, botão e exibição de título/data.</li>
                                <li>A tela Fotos deve usar o limite configurado em <code>screen_limit</code>.</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section id="mlpb-tab-about" class="mlpb-tab-panel <?php echo 'about' === $active_tab ? 'is-active' : ''; ?>" <?php echo 'about' === $active_tab ? '' : 'hidden'; ?>>
                    <div class="mlpb-card">
                        <div class="mlpb-card-header"><div><h2>Sobre / Institucional</h2><p class="mlpb-muted">Dados institucionais entregues pelo endpoint <code>/institutional</code>.</p></div></div>
                        <div class="mlpb-form-grid">
                            <?php $this->field( 'text', 'about_title', 'Título', $settings ); ?>
                            <?php $this->field( 'url', 'about_image', 'Imagem/Logo URL', $settings ); ?>
                            <?php $this->field( 'textarea', 'about_content', 'Texto', $settings ); ?>
                            <?php $this->field( 'text', 'about_address', 'Endereço', $settings ); ?>
                            <?php $this->field( 'text', 'about_phone', 'Telefone', $settings ); ?>
                            <?php $this->field( 'email', 'about_email', 'E-mail', $settings ); ?>
                            <?php $this->field( 'text', 'about_whatsapp', 'WhatsApp', $settings ); ?>
                            <?php $this->field( 'url', 'about_site', 'Site oficial', $settings ); ?>
                            <?php $this->field( 'url', 'about_instagram', 'Instagram', $settings ); ?>
                            <?php $this->field( 'url', 'about_facebook', 'Facebook', $settings ); ?>
                            <?php $this->field( 'url', 'about_youtube', 'YouTube', $settings ); ?>
                            <?php $this->field( 'url', 'about_tiktok', 'TikTok', $settings ); ?>
                            <?php $this->field( 'url', 'about_twitter', 'Twitter / X', $settings ); ?>
                            <?php $this->field( 'text', 'about_social_icon_color', 'Cor dos ícones sociais', $settings, '#990000' ); ?>
                            <?php $this->field( 'text', 'about_social_icon_background', 'Fundo dos ícones sociais', $settings, '#ffffff' ); ?>
                            <?php $this->field( 'text', 'about_social_icon_size', 'Tamanho dos ícones', $settings, 'small, medium ou large' ); ?>
                        </div>
                    </div>
                </section>

                <div class="mlpb-actions" style="margin-top:18px;">
                    <button type="submit" class="button button-primary">Salvar configurações</button>
                </div>
            </form>

            <section id="mlpb-tab-status" class="mlpb-tab-panel <?php echo 'status' === $active_tab ? 'is-active' : ''; ?>" <?php echo 'status' === $active_tab ? '' : 'hidden'; ?>>
                <div class="mlpb-grid mlpb-grid-2">
                    <div class="mlpb-card">
                        <div class="mlpb-card-header"><div><h2>Status técnico</h2><p class="mlpb-muted">Diagnóstico do motor da API.</p></div></div>
                        <p><strong>Version:</strong> <?php echo esc_html( ML_APP_BASE_CONTENT_VERSION ); ?></p>
                        <p><strong>API Namespace:</strong> <code>ml-app-base/v1</code></p>
                        <p><strong>ML Banner Pro detectado:</strong> <?php echo $ml_banner_active ? '<span class="mlpb-kbd">Sim</span>' : '<span class="mlpb-kbd">Não</span>'; ?></p>
                        <p><strong>Post type esperado:</strong> <code>mlcp_item</code></p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mlpb-actions">
                            <?php wp_nonce_field( 'ml_app_base_content_clear_cache' ); ?>
                            <input type="hidden" name="action" value="ml_app_base_content_clear_cache" />
                            <button type="submit" class="button button-secondary">Limpar cache da API</button>
                        </form>
                    </div>
                    <div class="mlpb-card">
                        <div class="mlpb-card-header"><div><h2>Endpoints</h2><p class="mlpb-muted">Rotas públicas preservadas.</p></div></div>
                        <ul class="mlpb-list">
                            <li><code>GET /wp-json/ml-app-base/v1/home</code></li>
                            <li><code>GET /wp-json/ml-app-base/v1/home-config</code></li>
                            <li><code>GET /wp-json/ml-app-base/v1/events</code> — ML Banner Pro</li>
                            <li><code>GET /wp-json/ml-app-base/v1/banners</code> — ML Banner Pro</li>
                            <li><code>GET /wp-json/ml-app-base/v1/news</code></li>
                            <li><code>GET /wp-json/ml-app-base/v1/photo-albums</code> — ML Gallery Pro</li>
                            <li><code>GET /wp-json/ml-app-base/v1/institutional</code> — dados, contatos, redes e cores dos ícones</li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>
        <?php
    }

    private function get_news_categories_for_selector() {
        $terms = get_categories( array(
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        if ( is_wp_error( $terms ) ) {
            return array();
        }

        return $terms;
    }

    private function news_category_selector( $settings, $categories ) {
        $selected = isset( $settings['home_news_category_slugs'] ) ? $settings['home_news_category_slugs'] : ML_App_Base_Content_Settings::news_category_slugs();
        if ( ! is_array( $selected ) ) {
            $selected = array_filter( array_map( 'trim', explode( ',', (string) $selected ) ) );
        }
        $selected = array_values( array_unique( array_map( 'sanitize_title', $selected ) ) );
        $category_map = array();
        foreach ( $categories as $category ) {
            $slug                  = sanitize_title( $category->slug );
            $category_map[ $slug ] = $category;
        }
        ?>
        <div class="mlpb-category-panel mlpb-field-full" data-news-category-selector>
            <div class="mlpb-category-head">
                <div>
                    <h3>Categorias exibidas em Notícias</h3>
                    <p class="mlpb-muted">Escolha as categorias do WordPress que alimentam a seção Notícias do app.</p>
                </div>
                <div class="mlpb-category-actions">
                    <button type="button" class="button mlpb-select-all-categories">Selecionar todas</button>
                    <button type="button" class="button mlpb-clear-categories">Limpar seleção</button>
                </div>
            </div>

            <div class="mlpb-category-picker">
                <label for="mlab-news-category-picker">Adicionar categoria</label>
                <select id="mlab-news-category-picker" class="mlpb-category-select" data-category-picker>
                    <option value="">Selecione uma categoria...</option>
                    <?php foreach ( $categories as $category ) :
                        $slug = sanitize_title( $category->slug );
                        ?>
                        <option value="<?php echo esc_attr( $slug ); ?>" data-name="<?php echo esc_attr( $category->name ); ?>">
                            <?php echo esc_html( $category->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mlpb-selected-category-area">
                <div class="mlpb-selected-category-head">
                    <span>Categorias selecionadas</span>
                    <small data-selected-count><?php echo esc_html( (string) count( $selected ) ); ?></small>
                </div>
                <div class="mlpb-selected-category-list" data-selected-category-list>
                    <?php foreach ( $selected as $slug ) :
                        if ( empty( $category_map[ $slug ] ) ) {
                            continue;
                        }
                        $category = $category_map[ $slug ];
                        ?>
                        <div class="mlpb-selected-category-item" data-category-slug="<?php echo esc_attr( $slug ); ?>">
                            <input type="hidden" name="home_news_category_slugs[]" value="<?php echo esc_attr( $slug ); ?>" />
                            <span><?php echo esc_html( $category->name ); ?></span>
                            <button type="button" class="mlpb-remove-category" aria-label="Remover <?php echo esc_attr( $category->name ); ?>">Remover</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="mlpb-empty-selected" data-empty-selected <?php echo empty( $selected ) ? '' : 'hidden'; ?>>Nenhuma categoria selecionada. O fallback seguro continuará ativo.</p>
            </div>

            <p class="mlpb-note"><strong>Fallback seguro:</strong> se nada for selecionado, a API tenta Notícias, Últimas Notícias e Feijoadas para não zerar o app.</p>
        </div>
        <?php
    }

    private function tab_button( $tab, $label, $active_tab ) {
        printf(
            '<button type="button" class="mlpb-tab-button %1$s" data-tab-target="mlpb-tab-%2$s" data-tab-value="%2$s">%3$s</button>',
            $tab === $active_tab ? 'is-active' : '',
            esc_attr( $tab ),
            esc_html( $label )
        );
    }

    private function checkbox( $key, $label, $settings ) {
        $html  = '<div class="mlpb-field mlpb-field-full">';
        $html .= '<label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1" ' . checked( ! empty( $settings[ $key ] ), true, false ) . ' /> ' . esc_html( $label ) . '</label>';
        $html .= '</div>';
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
    }

    private function field( $type, $key, $label, $settings, $placeholder = '' ) {
        $value = $settings[ $key ] ?? '';
        $html  = '<div class="mlpb-field ' . ( 'textarea' === $type ? 'mlpb-field-full' : '' ) . '">';
        $html .= '<label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';

        if ( 'textarea' === $type ) {
            $html .= '<textarea id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" placeholder="' . esc_attr( $placeholder ) . '">' . esc_textarea( $value ) . '</textarea>';
        } elseif ( 'number' === $type ) {
            $html .= '<input id="' . esc_attr( $key ) . '" type="number" min="1" max="20" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
        } else {
            $input_type = 'url' === $type ? 'url' : ( 'email' === $type ? 'email' : 'text' );
            $html .= '<input id="' . esc_attr( $key ) . '" type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
        }

        $html .= '</div>';
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
    }
}
