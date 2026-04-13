/* ── Constants ─────────────────────────────────────────────────────────── */
const PALETTE = [
  "#E63946", "#F77F88", "#FF9F5A", "#FFB84D", "#FFD166",
  "#F4D35E", "#EAE2B7", "#FCBF49", "#E76F51", "#D4A574",
  "#A8DADC", "#457B9D", "#1D3557", "#2A9D8F", "#264653",
  "#52B788", "#74C69D", "#95D5B2", "#52B788", "#B7E4C7",
  "#6A4C93", "#8E7CC3", "#C77DFF", "#E0AAFF", "#D08CE9",
  "#F48FB1", "#FF6F9F", "#A23B72", "#7209B7", "#3A0CA3"];
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'];

/* ── State ─────────────────────────────────────────────────────────────── */
let species = [];        // active species
let allSpecies = [];        // all available species from API

/* ── API Fetching ──────────────────────────────────────────────────────── */
async function fetchAllSpecies() {
  try {
    const res = await fetch('/species');
    allSpecies = await res.json();
  } catch (e) {
    console.error('Failed to fetch species:', e);
  }
}

async function fetchMonthlyData(speciesId) {
  try {
    const res = await fetch(`/species/${speciesId}/monthly`);
    return await res.json();
  } catch (e) {
    console.error('Failed to fetch monthly data:', e);
    return Array(12).fill(null).map(() => ({ count: 0, pictures: [] }));
  }
}

/* ── Scrolling header ──────────────────────────────────────────────────── */
const header = document.getElementById('appHeader');
window.addEventListener('scroll', () => {
  header.classList.toggle('scrolled', window.scrollY > 30);
}, { passive: true });

/* ── Species dropdown ──────────────────────────────────────────────────── */
const dropdown = document.getElementById('speciesDropdown');
const addBtn = document.getElementById('addSpeciesBtn');
const pickerWrap = document.getElementById('pickerWrapper');

addBtn.addEventListener('click', (e) => {
  e.stopPropagation();
  const isOpen = !dropdown.classList.contains('hidden');
  if (isOpen) { dropdown.classList.add('hidden'); return; }
  renderDropdownOptions();
  dropdown.classList.remove('hidden');
});

document.addEventListener('click', (e) => {
  if (!pickerWrap.contains(e.target)) dropdown.classList.add('hidden');
});

function renderDropdownOptions() {
  const selected = new Set(species.map(s => s.id));
  const ul = document.getElementById('speciesOptions');
  ul.innerHTML = '';
  allSpecies.forEach(sp => {
    const taken = selected.has(sp.id);
    const li = document.createElement('li');
    li.className = `px-3 py-2 text-sm flex items-center justify-between gap-2
      ${taken
        ? 'text-sage/50 cursor-default'
        : 'text-bark cursor-pointer hover:bg-sage/10 active:bg-sage/20'}`;
    li.innerHTML = `<span>${sp.common_name}</span>${taken ? '<span class="text-[10px] text-fern font-medium tracking-wide uppercase">added</span>' : ''}`;
    if (!taken) {
      li.addEventListener('click', () => {
        addSpecies(sp);
        dropdown.classList.add('hidden');
      });
    }
    ul.appendChild(li);
  });

  if (selected.size >= allSpecies.length) {
    ul.innerHTML = `<li class="px-3 py-2 text-sm text-sage/60 text-center">All species added</li>`;
  }
}

async function addSpecies(sp) {
  const idx = species.length;
  const monthlyData = await fetchMonthlyData(sp.id);
  const data = monthlyData.map(m => m.count);

  species.push({
    id: sp.id,
    name: sp.common_name,
    scientific_name: sp.scientific_name,
    data,
    monthlyData,
    color: PALETTE[Math.floor(Math.random() * PALETTE.length)],
  });
  renderAll();
}

/* ── Species tags (read-only) ──────────────────────────────────────────── */
function renderSpecies() {
  const list = document.getElementById('speciesList');
  list.innerHTML = '';

  species.forEach(sp => {
    const row = document.createElement('div');
    row.className = 'species-row flex items-center gap-2 bg-warm border border-fern/60 rounded-lg px-3 py-2';
    row.innerHTML = `
      <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:${sp.color}"></div>
      <span class="flex-1 text-sm font-medium text-bark">${sp.name}</span>
      <button class="remove-btn text-fern hover:text-amber transition-colors text-sm px-0.5" data-id="${sp.id}" title="Remove">✕</button>
    `;
    list.appendChild(row);
  });

  list.querySelectorAll('.remove-btn').forEach(btn =>
    btn.addEventListener('click', e => {
      species = species.filter(s => s.id !== +e.target.dataset.id);
      renderAll();
    })
  );

  addBtn.style.display = species.length >= allSpecies.length ? 'none' : '';
}

/* ── Chart.js ──────────────────────────────────────────────────────────── */
let chart = null;
function renderChart() {
  const canvas = document.getElementById('trendChart');
  if (chart) { chart.destroy(); chart = null; }

  chart = new Chart(canvas, {
    type: 'line',
    data: {
      labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
      datasets: species.map(sp => ({
        label: sp.name,
        data: sp.data,
        borderColor: sp.color,
        backgroundColor: sp.color + '20',
        borderWidth: 2.5,
        pointRadius: 3,
        pointHoverRadius: 6,
        pointBackgroundColor: sp.color,
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        tension: 0.4,
        fill: true,
      }))
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#2C1A0E',
          titleFont: { family: 'DM Sans', size: 11 },
          bodyFont: { family: 'DM Sans', size: 11 },
          padding: 9, cornerRadius: 8,
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(181,201,161,0.3)' },
          ticks: { color: '#7D9B6A', font: { family: 'DM Sans', size: 10 } },
          border: { display: false },
        },
        y: {
          grid: { color: 'rgba(181,201,161,0.3)' },
          ticks: { color: '#7D9B6A', font: { family: 'DM Sans', size: 10 }, maxTicksLimit: 5 },
          border: { display: false },
          beginAtZero: true,
        }
      }
    }
  });

  document.getElementById('chartLegend').innerHTML = species.length
    ? species.map(sp => `
        <div class="flex items-center gap-1.5">
          <div style="width:18px;height:3px;background:${sp.color};border-radius:2px"></div>
          <span class="text-xs">${sp.name}</span>
        </div>`).join('')
    : `<span class="text-xs text-sage/60">Add species to see trends</span>`;
}

/* ── Gallery ───────────────────────────────────────────────────────────── */
function buildMonthData() {
  return MONTHS.map((month, mi) => {
    const photos = [];
    species.forEach(sp => {
      const monthData = sp.monthlyData[mi];
      if (monthData && monthData.pictures) {
        monthData.pictures.forEach(pic => {
          photos.push({
            speciesId: sp.id,
            species: sp.name,
            color: sp.color,
            pictureId: pic.id,
            datetime: pic.datetime
          });
        });
      }
    });
    // Shuffle photos for random order
    return { month, photos: shuffleArray(photos) };
  }).filter(m => m.photos.length > 0);
}

// Fisher-Yates shuffle
function shuffleArray(arr) {
  const result = [...arr];
  for (let i = result.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [result[i], result[j]] = [result[j], result[i]];
  }
  return result;
}

function renderGallery() {
  const container = document.getElementById('galleryContainer');
  const data = buildMonthData();

  if (!data.length) {
    container.innerHTML = `
      <div class="text-center py-10 text-sage">
        <div class="text-4xl mb-2">🔭</div>
        <p class="text-sm">Select a species above to see photos.</p>
      </div>`;
    return;
  }

  container.innerHTML = '';
  data.forEach(({ month, photos }) => {
    const group = document.createElement('div');
    group.className = 'mb-5';

    const header = document.createElement('div');
    header.className = 'flex items-baseline justify-between mb-2 pb-1.5 border-b border-fern';
    header.innerHTML = `
      <span class="font-serif italic text-base text-moss">${month}</span>
      <span class="text-[11px] text-sage">${photos.length} photo${photos.length !== 1 ? 's' : ''}</span>`;
    group.appendChild(header);

    const scrollContainer = document.createElement('div');
    scrollContainer.className = 'gallery-scroll-container';

    const scrollTrack = document.createElement('div');
    scrollTrack.className = 'gallery-scroll-track';

    photos.forEach(p => {
      const card = createPhotoCard(p, month);
      scrollTrack.appendChild(card);
    });

    scrollContainer.appendChild(scrollTrack);
    group.appendChild(scrollContainer);
    container.appendChild(group);

    // Setup scroll handlers
    setupScrollHandlers(scrollContainer, scrollTrack);
    // Initialize lazy loading for visible images
    initLazyLoading(scrollTrack);
  });
}

function createPhotoCard(p, month) {
  const card = document.createElement('div');
  card.className = 'photo-card gallery-item relative rounded-lg overflow-hidden cursor-pointer border border-fern/50 bg-cream flex-shrink-0';
  card.style.width = '160px';
  card.style.height = '160px';
  card.style.aspectRatio = '1';

  const img = document.createElement('img');
  img.className = 'w-full h-full object-cover lazy-image';
  img.dataset.src = `/pictures/${p.pictureId}/thumbnail`;
  img.alt = p.species;
  // Set a placeholder before lazy loading
  img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 160"%3E%3Crect fill="%23F5EFE3" width="160" height="160"/%3E%3C/svg%3E';

  const badge = document.createElement('div');
  badge.className = 'absolute bottom-1 left-1 text-white text-[9px] font-medium px-1.5 py-0.5 rounded truncate';
  badge.style.cssText = 'background:rgba(44,26,14,0.65);backdrop-filter:blur(3px);max-width:calc(100% - 8px)';
  badge.textContent = p.species;

  card.appendChild(img);
  card.appendChild(badge);

  card.addEventListener('click', () => openModal(p, month));

  return card;
}

// Lazy loading with Intersection Observer
const imageObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const img = entry.target;
      const src = img.dataset.src;
      if (src) {
        img.src = src;
        img.classList.add('loaded');
        imageObserver.unobserve(img);
      }
    }
  });
}, { rootMargin: '50px' });

function initLazyLoading(container) {
  container.querySelectorAll('.lazy-image').forEach(img => {
    imageObserver.observe(img);
  });
}

function setupScrollHandlers(scrollContainer, scrollTrack) {
  // Horizontal scroll with wheel
  scrollContainer.addEventListener('wheel', (e) => {
    e.preventDefault();
    const scrollAmount = 200; // pixels per scroll
    scrollContainer.scrollLeft += (e.deltaY > 0 ? scrollAmount : -scrollAmount);
  }, { passive: false });

  // Touch scroll support (already native, but ensure smooth behavior)
  let touchStartX = 0;
  scrollContainer.addEventListener('touchstart', (e) => {
    touchStartX = e.touches[0].clientX;
  }, { passive: true });

  scrollContainer.addEventListener('touchmove', (e) => {
    // Native touch scroll works automatically
  }, { passive: true });
}

/* ── Modal ─────────────────────────────────────────────────────────────── */
function openModal(p, month) {
  document.getElementById('modalSpecies').textContent = p.species;
  document.getElementById('modalMonth').textContent = month;
  document.getElementById('modalImage').src = `/pictures/${p.pictureId}`;
  const modal = document.getElementById('modal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  document.body.style.overflow = 'hidden';
}
function closeModal() {
  document.getElementById('modal').classList.replace('flex', 'hidden');
  document.body.style.overflow = '';
}
document.getElementById('modal').addEventListener('click', e => { if (e.target === document.getElementById('modal')) closeModal(); });
document.getElementById('modalClose').addEventListener('click', closeModal);
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

/* ── Boot ──────────────────────────────────────────────────────────────── */
function toggleDescriptionCTA() {
  const cta = document.getElementById('descriptionCTA');
  if (species.length > 0) {
    cta.classList.add('hidden');
  } else {
    cta.classList.remove('hidden');
  }
}

function renderAll() {
  renderSpecies();
  toggleDescriptionCTA();
  renderChart();
  renderGallery();
}

// Initialize: fetch species and boot
(async () => {
  await fetchAllSpecies();
  renderAll();
})();
