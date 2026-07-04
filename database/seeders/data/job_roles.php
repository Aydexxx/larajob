<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Demo Job Role Catalog
|--------------------------------------------------------------------------
|
| Realistic, hand-written job listings used ONLY by EmployerSeeder to
| populate the local/testing demo. This is never touched by the real
| employer "post a job" flow — those listings are authored by employers.
|
| Each entry pairs professional description/requirements prose with an
| explicit, structured `skills` list. EmployerSeeder appends that list to
| the requirements text verbatim ("Must-have skills: ..."), which is exactly
| what the deterministic matcher (MatchService::structuredOverlap) scans, so
| the "X of N skills matched" counter reflects genuine overlap with candidate
| profiles instead of the near-zero counts the old lorem-ipsum text produced.
|
| The skill vocabulary is deliberately drawn from the candidate skill sets in
| CandidateProfileFactory so seeded candidates and jobs share real terms.
|
*/

return [
    [
        'title' => 'Senior Backend Engineer',
        'skills' => ['PHP', 'Laravel', 'PostgreSQL', 'Redis', 'REST APIs', 'Docker'],
        'description' => <<<'TEXT'
        We're looking for a Senior Backend Engineer to own the services that power our platform end to end. You'll design and ship APIs used by millions of requests a day, shape our data model as the product grows, and set the standard for how we write, test, and operate backend code.

        Day to day you'll build new features in our Laravel monolith, break out services where it makes sense, tune slow PostgreSQL queries, and add the caching and queueing that keep response times low under load. You'll review pull requests, mentor mid-level engineers, and have a real say in the technical direction of the team.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 5+ years of professional backend experience and have shipped and maintained production PHP applications, ideally with Laravel. You write clean, well-tested code and are comfortable owning a feature from design through deployment and on-call.

        You know your way around a relational database, can reason about indexes and query plans in PostgreSQL, and reach for Redis and queues to keep things fast. Experience designing and versioning REST APIs and running services in Docker is expected.
        TEXT,
    ],
    [
        'title' => 'Frontend Engineer',
        'skills' => ['JavaScript', 'TypeScript', 'React', 'GraphQL', 'CSS', 'REST APIs'],
        'description' => <<<'TEXT'
        Join our product team as a Frontend Engineer building the interfaces our customers live in every day. You'll turn Figma designs into fast, accessible, pixel-accurate React applications and partner closely with designers and backend engineers to ship features end to end.

        We care about craft: smooth interactions, sensible loading states, and a component library that scales. You'll help evolve our design system, keep our TypeScript codebase healthy, and make sure everything we ship works well on every screen.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 3+ years building production web apps with React and modern JavaScript, and you're fluent in TypeScript. You write semantic, accessible markup and are confident with CSS and responsive layouts.

        You've consumed REST APIs and GraphQL, understand client-side state and performance, and have opinions about testing and component design. Bonus points for caring deeply about accessibility and web performance budgets.
        TEXT,
    ],
    [
        'title' => 'Python Backend Engineer',
        'skills' => ['Python', 'Django', 'FastAPI', 'PostgreSQL', 'Redis', 'Celery'],
        'description' => <<<'TEXT'
        We're hiring a Python Backend Engineer to build and scale the services behind our product. You'll work across our Django and FastAPI codebases, design clean APIs, and make sure our background processing and data layer hold up as we grow.

        You'll ship real features to customers, from the database schema up to the endpoint, and help us keep a large Python codebase maintainable, well tested, and fast.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 4+ years of professional Python experience and have built production services with Django or FastAPI. You're comfortable modelling data in PostgreSQL and writing efficient queries.

        You've used Redis for caching and Celery (or a similar queue) for background work, and you write code with tests. You understand how to design HTTP APIs that other teams can build on with confidence.
        TEXT,
    ],
    [
        'title' => 'Java Platform Engineer',
        'skills' => ['Java', 'Spring Boot', 'Kubernetes', 'AWS', 'Kafka', 'Microservices'],
        'description' => <<<'TEXT'
        Our platform team is looking for a Java Platform Engineer to build the backbone services other teams depend on. You'll design and operate microservices that handle high throughput, own critical parts of our event pipeline, and help shape how we build on the JVM.

        This is a role for someone who enjoys distributed systems: getting the boundaries right, keeping services observable, and making the platform something the rest of engineering can move fast on.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 5+ years of backend experience with Java and have built and run services with Spring Boot in production. You're comfortable designing microservices and thinking through the failure modes of distributed systems.

        You've worked with Kafka or a similar streaming platform, deployed to Kubernetes, and run workloads on AWS. You know how to keep services observable and reliable under real traffic.
        TEXT,
    ],
    [
        'title' => 'Golang Engineer',
        'skills' => ['Go', 'gRPC', 'Kubernetes', 'PostgreSQL', 'Docker', 'Prometheus'],
        'description' => <<<'TEXT'
        We're building high-performance services in Go and want an engineer who loves the language to join us. You'll design gRPC APIs, build services that stay fast under heavy concurrency, and help us keep our infrastructure simple and observable.

        You'll have room to influence architecture, from how we structure our Go modules to how we roll services out on Kubernetes.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 3+ years writing production Go and understand its concurrency model well. You've designed APIs with gRPC and are comfortable with PostgreSQL as your data store.

        You're at home packaging services with Docker, deploying to Kubernetes, and instrumenting them with Prometheus so you can see what's happening in production.
        TEXT,
    ],
    [
        'title' => 'Full Stack Engineer',
        'skills' => ['Ruby', 'Rails', 'PostgreSQL', 'React', 'Sidekiq', 'JavaScript'],
        'description' => <<<'TEXT'
        As a Full Stack Engineer you'll work across the whole stack of our Rails product — from the PostgreSQL schema and background jobs to the React front end customers interact with. You'll ship features that touch every layer and take real ownership of the outcome.

        We're a small, fast-moving team, so you'll get a lot of surface area and the chance to shape the product as much as the code.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 3+ years of full stack experience and have built and maintained a production Ruby on Rails application backed by PostgreSQL. You're comfortable dropping into React and modern JavaScript on the front end.

        You've used background processing such as Sidekiq, write tests as a matter of habit, and are happy owning a feature from database to UI.
        TEXT,
    ],
    [
        'title' => 'iOS Engineer',
        'skills' => ['Swift', 'SwiftUI', 'UIKit', 'CoreData', 'Combine', 'REST APIs'],
        'description' => <<<'TEXT'
        We're looking for an iOS Engineer to help build a flagship app used by hundreds of thousands of people. You'll craft smooth, delightful interfaces in SwiftUI, integrate with our backend APIs, and care about the details that make an app feel native.

        You'll work closely with design and product, own features end to end, and help raise the bar for quality across our mobile codebase.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 3+ years of professional iOS development in Swift and have shipped apps to the App Store. You're fluent in SwiftUI and comfortable maintaining older UIKit screens.

        You've persisted data with CoreData, handled async flows with Combine, and integrated REST APIs. You care about performance, accessibility, and a clean, testable architecture.
        TEXT,
    ],
    [
        'title' => 'Android Engineer',
        'skills' => ['Kotlin', 'Android', 'Jetpack Compose', 'Firebase', 'Coroutines', 'REST APIs'],
        'description' => <<<'TEXT'
        Join our mobile team as an Android Engineer building a modern, Compose-first app. You'll design and ship features in Kotlin, integrate with our services, and help us keep the app fast and reliable across a huge range of devices.

        You'll partner with designers and backend engineers, own features from idea to release, and help shape our Android architecture as the app grows.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 3+ years of professional Android development in Kotlin and have shipped apps on the Play Store. You're building UI with Jetpack Compose and handling concurrency with Coroutines.

        You've integrated REST APIs and worked with Firebase for things like auth, analytics, or messaging. You write tests and care about a clean, maintainable codebase.
        TEXT,
    ],
    [
        'title' => 'Product Designer',
        'skills' => ['Figma', 'Prototyping', 'User Research', 'Design Systems', 'Sketch', 'Adobe XD'],
        'description' => <<<'TEXT'
        We're hiring a Product Designer to own the end-to-end experience of major parts of our product. You'll run discovery, shape flows, and deliver polished, production-ready designs in Figma that engineers can build with confidence.

        You'll contribute to and help evolve our design system, validate ideas with real users, and work hand in hand with product and engineering from first sketch to launch.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 4+ years designing digital products and a portfolio that shows the thinking behind your work, not just the final screens. You're an expert in Figma and comfortable moving from low-fidelity prototypes to detailed specs.

        You've planned and run user research, contributed to design systems, and can communicate and defend your decisions with stakeholders. Familiarity with tools like Sketch or Adobe XD is a plus.
        TEXT,
    ],
    [
        'title' => 'Data Engineer',
        'skills' => ['SQL', 'Apache Spark', 'Airflow', 'dbt', 'Snowflake', 'Python'],
        'description' => <<<'TEXT'
        We're looking for a Data Engineer to build the pipelines that turn raw events into trustworthy, analysis-ready data. You'll design and operate batch and streaming workflows, model our warehouse, and make sure the whole company can rely on the numbers.

        You'll work with analysts, data scientists, and product engineers to make data a first-class part of how we build and decide.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 4+ years in data engineering with strong SQL and Python. You've built and operated pipelines at scale with tools like Apache Spark and orchestrated them with Airflow.

        You've modelled warehouse data with dbt and worked with a cloud warehouse such as Snowflake. You care about data quality, testing, and pipelines that are observable and easy to operate.
        TEXT,
    ],
    [
        'title' => 'DevOps Engineer',
        'skills' => ['Terraform', 'AWS', 'CI/CD', 'Kubernetes', 'Docker', 'Prometheus'],
        'description' => <<<'TEXT'
        Join us as a DevOps Engineer to build the infrastructure and tooling that let our teams ship safely and often. You'll own our cloud footprint as code, harden our deployment pipelines, and make production something engineers trust.

        You'll be the person who makes the right thing the easy thing — golden paths, good defaults, and observability that turns incidents into quick fixes.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 4+ years in DevOps, SRE, or platform engineering. You manage infrastructure as code with Terraform on AWS and are comfortable operating containerised workloads on Kubernetes.

        You've built CI/CD pipelines that teams rely on, package services with Docker, and instrument systems with Prometheus and modern monitoring. You bring a calm, systematic approach to reliability and on-call.
        TEXT,
    ],
    [
        'title' => 'QA Automation Engineer',
        'skills' => ['Selenium', 'Cypress', 'Playwright', 'Jest', 'CI/CD', 'JavaScript'],
        'description' => <<<'TEXT'
        We're hiring a QA Automation Engineer to help us ship quickly without breaking things. You'll build and maintain end-to-end and integration test suites, wire them into our pipelines, and give engineers fast, trustworthy feedback on every change.

        You'll partner with product engineers to raise the quality bar, close gaps in coverage, and make flaky tests a thing of the past.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 3+ years in test automation and can write maintainable suites in JavaScript. You've built end-to-end tests with tools like Cypress, Playwright, or Selenium and unit tests with Jest.

        You've integrated tests into CI/CD so they run on every pull request, and you know how to hunt down flakiness and keep suites fast and reliable.
        TEXT,
    ],
    [
        'title' => 'Machine Learning Engineer',
        'skills' => ['Python', 'TensorFlow', 'PyTorch', 'Scikit-learn', 'Pandas', 'SQL'],
        'description' => <<<'TEXT'
        We're looking for a Machine Learning Engineer to take models from notebook to production. You'll build and evaluate models, design the data and serving pipelines around them, and measure real impact on the product.

        You'll work end to end — framing the problem, wrangling the data, training and validating models, and shipping them behind reliable, monitored services.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 3+ years applying machine learning in production with strong Python skills. You're comfortable with the scientific stack — Pandas, Scikit-learn — and deep learning frameworks such as TensorFlow or PyTorch.

        You write solid SQL to get at the data you need, understand how to evaluate models honestly, and care about reproducibility, monitoring, and the engineering around the model, not just the model itself.
        TEXT,
    ],
    [
        'title' => '.NET Engineer',
        'skills' => ['C#', '.NET', 'Azure', 'SQL Server', 'Entity Framework', 'REST APIs'],
        'description' => <<<'TEXT'
        Join our team as a .NET Engineer building robust services and applications on modern .NET. You'll design clean APIs in C#, work with our data layer, and deliver features that hold up in production for enterprise customers.

        You'll take ownership of features end to end and help us keep a large .NET codebase clean, tested, and a pleasure to work in.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 4+ years of professional C# and .NET experience building production applications. You design and consume REST APIs and work comfortably with SQL Server and Entity Framework.

        You've deployed and run applications on Azure and write well-tested, maintainable code. You care about clean architecture and delivering reliable software.
        TEXT,
    ],
    [
        'title' => 'WordPress Developer',
        'skills' => ['PHP', 'WordPress', 'WooCommerce', 'MySQL', 'CSS', 'JavaScript'],
        'description' => <<<'TEXT'
        We're looking for a WordPress Developer to build and maintain high-traffic sites and online stores for our clients. You'll craft custom themes and plugins, tune performance, and make sure everything is secure, accessible, and easy for editors to run.

        You'll own projects end to end, work directly with clients and designers, and take pride in sites that are fast and genuinely pleasant to use.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 3+ years building custom WordPress sites in PHP and are comfortable writing your own themes and plugins rather than relying on page builders. You know WordPress and WooCommerce inside out.

        You're solid with MySQL, write clean CSS and JavaScript, and care about performance, security, and a good editing experience for the people who run the site.
        TEXT,
    ],
    [
        'title' => 'Full Stack JavaScript Engineer',
        'skills' => ['JavaScript', 'TypeScript', 'Node.js', 'React', 'PostgreSQL', 'GraphQL'],
        'description' => <<<'TEXT'
        We're hiring a Full Stack JavaScript Engineer to build features across our Node.js and React stack. You'll design GraphQL and REST APIs on the backend, build the interfaces that consume them on the front end, and own features from database to browser.

        You'll work in a modern TypeScript codebase, help shape our architecture, and ship things customers feel every week.
        TEXT,
        'requirements' => <<<'TEXT'
        You have 4+ years of full stack experience with JavaScript and TypeScript across Node.js and React. You've designed APIs — REST and GraphQL — and modelled data in PostgreSQL.

        You write tested, maintainable code, care about performance on both sides of the wire, and enjoy owning a feature end to end.
        TEXT,
    ],
];
